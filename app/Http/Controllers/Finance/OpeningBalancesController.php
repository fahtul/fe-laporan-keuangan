<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Helpers\FinanceApiHelper;
use Illuminate\Http\Request;

class OpeningBalancesController extends Controller
{
    private function fetchAccountsMap(): array
    {
        $res = FinanceApiHelper::get('/v1/accounts', [
            'page' => 1,
            'limit' => 1000,
            'q' => '',
        ]);

        if (!($res['success'] ?? false)) {
            return [[], $res['message'] ?? 'Failed to load accounts'];
        }

        $items = data_get($res, 'data.data', []);
        $map = collect($items)
            ->mapWithKeys(function ($a) {
                $id = $a['id'] ?? null;
                if (!$id) return [];
                return [
                    $id => [
                        'id' => $id,
                        'code' => $a['code'] ?? '',
                        'name' => $a['name'] ?? '',
                        'type' => $a['type'] ?? '',
                    ],
                ];
            })
            ->all();

        return [$map, null];
    }

    private function fetchPostableAccounts(): array
    {
        // pakai endpoint accounts yang sudah ada (bukan /options)
        $res = FinanceApiHelper::get('/v1/accounts', [
            'page' => 1,
            'limit' => 1000,
            'q' => '',
        ]);

        if (!($res['success'] ?? false)) {
            return [[], $res['message'] ?? 'Failed to load accounts'];
        }

        // response accounts kamu: $res['data']['data'] = items
        $items = data_get($res, 'data.data', []);
        $accounts = collect($items)
            ->map(function ($a) {
                return [
                    'id' => $a['id'] ?? null,
                    'code' => $a['code'] ?? '',
                    'name' => $a['name'] ?? '',
                    'type' => $a['type'] ?? '',
                    'is_postable' => (bool)($a['is_postable'] ?? true),
                    'requires_bp' => (bool)($a['requires_bp'] ?? false),
                    'subledger' => $a['subledger'] ?? null,
                ];
            })
            ->filter(fn ($a) => !empty($a['id']) && (bool)($a['is_postable'] ?? true) === true)
            ->values()
            ->all();

        return [$accounts, null];
    }

    private function buildLinesFromRequest(Request $request): array
    {
        $ids   = $request->input('line_account_id', []);
        $deb   = $request->input('line_debit', []);
        $cred  = $request->input('line_credit', []);
        $memos = $request->input('line_memo', []);
        $bpIds = $request->input('line_bp_id', []);

        $lines = [];
        $count = max(count($ids), count($deb), count($cred), count($memos), count($bpIds));

        for ($i = 0; $i < $count; $i++) {
            $accountId = $ids[$i] ?? null;
            $debit  = (float) ($deb[$i] ?? 0);
            $credit = (float) ($cred[$i] ?? 0);
            $memo   = $memos[$i] ?? null;
            $bpId   = $bpIds[$i] ?? null;

            // skip row kosong total
            if (!$accountId && $debit == 0 && $credit == 0 && empty($memo) && empty($bpId)) continue;

            if (!$accountId) {
                throw new \InvalidArgumentException("Line #".($i+1).": account is required");
            }

            $debit = max(0, $debit);
            $credit = max(0, $credit);

            if ($debit > 0 && $credit > 0) {
                throw new \InvalidArgumentException("Line #".($i+1).": debit and credit cannot both be > 0");
            }
            if ($debit <= 0 && $credit <= 0) {
                throw new \InvalidArgumentException("Line #".($i+1).": either debit or credit must be > 0");
            }

            $bpId = $bpId !== null ? trim((string) $bpId) : '';

            $lines[] = [
                'account_id' => $accountId,
                'bp_id' => $bpId !== '' ? $bpId : null,
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
                'memo' => ($memo !== null && trim((string)$memo) !== '') ? $memo : null,
            ];
        }

        if (count($lines) < 2) {
            throw new \InvalidArgumentException("Opening balance must have at least 2 lines");
        }

        return $lines;
    }

    public function index(Request $request)
    {
        $year = (string) $request->query('year', now()->format('Y'));

        $res = FinanceApiHelper::get('/v1/opening-balances', [
            'opening_key' => $year,
        ]);

        // API opening-balances backend: { status, data: entry|null }
        $opening = null;
        if (($res['success'] ?? false)) {
            $opening = data_get($res, 'data.data', null);
        }

        $accountsById = [];
        if ($opening) {
            [$accountsById, $accErr] = $this->fetchAccountsMap();
            // kalau gagal load accounts, tetap render page; fallback akan tampil ID
            unset($accErr);
        }

        return view('finance.opening_balances.index', [
            'year' => $year,
            'opening' => $opening,
            'accountsById' => $accountsById,
            'apiError' => ($res['success'] ?? false) ? null : ($res['message'] ?? 'Failed'),
        ]);
    }

    public function create(Request $request)
    {
        [$accounts, $accountsError] = $this->fetchPostableAccounts();

        $year = (string) $request->query('year', now()->format('Y'));
        $defaultDate = $year . '-01-01';

        return view('finance.opening_balances.create', [
            'accounts' => $accounts,
            'accountsError' => $accountsError,
            'year' => $year,
            'defaultDate' => $defaultDate,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'opening_key' => 'required|string|max:20',
            'date' => 'required|date',
            'memo' => 'nullable|string|max:2000',

            'line_account_id' => 'required|array|min:2',
            'line_debit' => 'required|array',
            'line_credit' => 'required|array',
            'line_memo' => 'nullable|array',
            'line_bp_id' => 'nullable|array',
            'line_bp_id.*' => 'nullable|string|max:100',
        ]);

        try {
            $lines = $this->buildLinesFromRequest($request);
        } catch (\Throwable $e) {
            return back()->withErrors(['api' => $e->getMessage()])->withInput();
        }

        $payload = [
            'opening_key' => (string) $request->input('opening_key'),
            'date' => $request->input('date'),
            'memo' => $request->input('memo') ?: null,
            'lines' => $lines, // IMPORTANT: tetap array
        ];

        $res = FinanceApiHelper::post('/v1/opening-balances', $payload);

        if (!($res['success'] ?? false)) {
            return back()->withErrors(['api' => $res['message'] ?? 'Gagal'])->withInput();
        }

        $id = data_get($res, 'data.data.id'); // response create: { status, data: entry }
        if ($id) {
            // kalau kamu punya route journal entry edit
            return redirect()
                ->route('finance.journal_entries.edit', $id)
                ->with('success', 'Opening balance berhasil dibuat & POSTED');
        }

        return redirect()
            ->route('finance.opening_balances.index', ['year' => $payload['opening_key']])
            ->with('success', 'Opening balance berhasil dibuat & POSTED');
    }
}

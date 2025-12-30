<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Helpers\FinanceApiHelper;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class JournalEntriesController extends Controller
{
    private function fetchAccountOptions(): array
    {
        // ambil postable accounts untuk dropdown lines
        $res = FinanceApiHelper::get('/v1/accounts/options', [
            'q' => '',
            'limit' => 200,
            'include_inactive' => 'false',
        ]);

        if (!($res['success'] ?? false)) {
            return [[], $res['message'] ?? 'Failed to load account options'];
        }

        $items = data_get($res, 'data.data', []);
        // filter postable
        $items = collect($items)
            ->filter(fn ($a) => (bool) data_get($a, 'is_postable') === true)
            ->values()
            ->all();

        return [$items, null];
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
            $debit = (float) ($deb[$i] ?? 0);
            $credit = (float) ($cred[$i] ?? 0);
            $memo = $memos[$i] ?? null;
            $bpId = $bpIds[$i] ?? null;

            // skip empty rows
            if (!$accountId && $debit == 0 && $credit == 0 && empty($memo)) {
                continue;
            }

            if (!$accountId) {
                throw new \InvalidArgumentException("Line #" . ($i + 1) . ": account is required");
            }

            // normalize negative
            $debit = max(0, $debit);
            $credit = max(0, $credit);

            // rule: only one side per line
            if ($debit > 0 && $credit > 0) {
                throw new \InvalidArgumentException("Line #" . ($i + 1) . ": debit and credit cannot both be > 0");
            }

            // rule: must have amount
            if ($debit <= 0 && $credit <= 0) {
                throw new \InvalidArgumentException("Line #" . ($i + 1) . ": either debit or credit must be > 0");
            }

            $lines[] = [
                'account_id' => $accountId,
                'debit' => $debit,
                'credit' => $credit,
                'memo' => $memo ?: null,
                'bp_id' => $bpId ?: null,
            ];
        }

        if (count($lines) < 2) {
            throw new \InvalidArgumentException("Journal entry must have at least 2 lines");
        }

        return $lines;
    }

    private function calcTotals(array $lines): array
    {
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $l) {
            $totalDebit += (float) ($l['debit'] ?? 0);
            $totalCredit += (float) ($l['credit'] ?? 0);
        }

        // compare 2 decimal precision
        $balanced = (round($totalDebit, 2) === round($totalCredit, 2)) && $totalDebit > 0;

        return [$totalDebit, $totalCredit, $balanced];
    }

    public function index(Request $request)
    {
        $q = (string) $request->query('q', '');
        $status = (string) $request->query('status', '');
        $from = (string) $request->query('from_date', '');
        $to = (string) $request->query('to_date', '');

        $page = (int) $request->query('page', 1);
        $limit = (int) $request->query('limit', 20);

        $res = FinanceApiHelper::get('/v1/journal-entries', [
            'q' => $q,
            'status' => $status,
            'from_date' => $from,
            'to_date' => $to,
            'page' => $page,
            'limit' => $limit,
        ]);

        $items = data_get($res, 'data.data', []);
        $meta  = data_get($res, 'data.meta', [
            'total' => 0,
            'page' => $page,
            'limit' => $limit,
        ]);

        $paginator = new LengthAwarePaginator(
            $items,
            (int) data_get($meta, 'total', 0),
            (int) data_get($meta, 'limit', $limit),
            (int) data_get($meta, 'page', $page),
            [
                'path' => route('finance.journal_entries.index'),
                'query' => $request->query(),
            ]
        );

        return view('finance.journal_entries.index', [
            'entries' => $paginator,
            'q' => $q,
            'status' => $status,
            'from_date' => $from,
            'to_date' => $to,
            'limit' => $limit,
            'apiError' => ($res['success'] ?? false) ? null : ($res['message'] ?? 'Failed'),
        ]);
    }

    public function create()
    {
        [$accounts, $accountsError] = $this->fetchAccountOptions();

        return view('finance.journal_entries.create', [
            'accounts' => $accounts,
            'accountsError' => $accountsError,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'memo' => 'nullable|string|max:500',
            'line_account_id' => 'required|array|min:2',
            'line_debit' => 'required|array',
            'line_credit' => 'required|array',
            'line_memo' => 'nullable|array',
            'line_bp_id' => 'nullable|array',
            'line_bp_id.*' => 'nullable|string|max:100',
            'submit_action' => 'nullable|in:draft,post',
        ]);

        $action = (string) $request->input('submit_action', 'draft'); // draft|post

        try {
            $lines = $this->buildLinesFromRequest($request);
        } catch (\Throwable $e) {
            return back()->withErrors(['api' => $e->getMessage()])->withInput();
        }

        // kalau user klik POST, wajib balanced
        [$td, $tc, $balanced] = $this->calcTotals($lines);
        if ($action === 'post' && !$balanced) {
            return back()->withErrors([
                'api' => "Tidak bisa POST: total debit harus sama dengan total credit dan > 0. (Debit={$td}, Credit={$tc})"
            ])->withInput();
        }

        // create dulu (draft)
        $payload = [
            'date' => $request->input('date'),
            'memo' => $request->input('memo') ?: null,
            'lines' => $lines,
        ];

        $res = FinanceApiHelper::post('/v1/journal-entries', $payload);

        if (!($res['success'] ?? false)) {
            return back()->withErrors(['api' => $res['message'] ?? 'Gagal'])->withInput();
        }

        $id = data_get($res, 'data.data.id');

        // jika action = post, langsung post setelah create
        if ($action === 'post') {
            $idemKey = (string) Str::uuid();

            $postRes = $res = FinanceApiHelper::postObject("/v1/journal-entries/{$id}/post", [], ['Idempotency-Key' => $idemKey]);
            
            // dd($postRes);

            if (!($postRes['success'] ?? false)) {
                // entry sudah terbuat draft, arahkan ke edit biar user bisa lihat + coba post ulang
                return redirect()
                    ->route('finance.journal_entries.edit', $id)
                    ->withErrors(['api' => $postRes['message'] ?? 'Gagal post'])
                    ->withInput();
            }

            return redirect()
                ->route('finance.journal_entries.edit', $id)
                ->with('success', 'Journal entry dibuat dan berhasil di-POST');
        }

        return redirect()
            ->route('finance.journal_entries.edit', $id)
            ->with('success', 'Journal entry dibuat (draft)');
    }

    public function edit(string $id)
    {
        $res = FinanceApiHelper::get("/v1/journal-entries/{$id}");

        if (!($res['success'] ?? false)) {
            abort(500, $res['message'] ?? 'Failed');
        }

        $entry = data_get($res, 'data.data', []);
        [$accounts, $accountsError] = $this->fetchAccountOptions();

        // idempotency key untuk POST button (biar retry submit tetap sama)
        $idemKey = (string) Str::uuid();

        return view('finance.journal_entries.edit', [
            'entry' => $entry,
            'accounts' => $accounts,
            'accountsError' => $accountsError,
            'idemKey' => $idemKey,
        ]);
    }

    public function update(string $id, Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'memo' => 'nullable|string|max:500',
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
            'date' => $request->input('date'),
            'memo' => $request->input('memo') ?: null,
            'lines' => $lines,
        ];

        $res = FinanceApiHelper::put("/v1/journal-entries/{$id}", $payload);

        if (!($res['success'] ?? false)) {
            return back()->withErrors(['api' => $res['message'] ?? 'Gagal'])->withInput();
        }

        return redirect()
            ->route('finance.journal_entries.edit', $id)
            ->with('success', 'Journal entry diupdate');
    }

    public function post(string $id, Request $request)
    {
        $idemKey = $request->input('idempotency_key') ?: (string) Str::uuid();

        $res = FinanceApiHelper::post(
            "/v1/journal-entries/{$id}/post",
            [],
            ['Idempotency-Key' => $idemKey]
        );

        if (!($res['success'] ?? false)) {
            return back()->withErrors(['api' => $res['message'] ?? 'Gagal post'])->withInput();
        }

        return redirect()
            ->route('finance.journal_entries.edit', $id)
            ->with('success', 'Journal entry berhasil di-POST');
    }

    public function reverse(string $id, Request $request)
    {
        $payload = $request->validate([
            'date' => 'nullable|date',
            'memo' => 'nullable|string|max:500',
        ]);

        $res = FinanceApiHelper::post("/v1/journal-entries/{$id}/reverse", [
            'date' => $payload['date'] ?? null,
            'memo' => $payload['memo'] ?? null,
        ]);

        if (!($res['success'] ?? false)) {
            return back()->withErrors(['api' => $res['message'] ?? 'Gagal reverse'])->withInput();
        }

        $newId = data_get($res, 'data.data.id');
        return redirect()
            ->route('finance.journal_entries.edit', $newId)
            ->with('success', 'Reversing entry dibuat (draft)');
    }
}

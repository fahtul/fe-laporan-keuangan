<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Helpers\FinanceApiHelper;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class AccountsController extends Controller
{
    private function unwrapData($json)
    {
        if (!is_array($json)) {
            return null;
        }

        // Some endpoints return: {status, data: ..., meta: ...}
        // Keep meta when it exists so pagination works.
        if (array_key_exists('status', $json) && array_key_exists('data', $json)) {
            if (array_key_exists('meta', $json)) {
                return [
                    'data' => $json['data'],
                    'meta' => $json['meta'],
                ];
            }

            return $json['data'];
        }

        return $json;
    }

    private function fetchParents()
    {
        // ambil banyak untuk dropdown parent
        $res = FinanceApiHelper::get('/v1/accounts/options', [
            'q' => '',
            'limit' => 2000,
            'include_inactive' => 'false',
        ]);

        if (!($res['success'] ?? false)) {
            return [[], $res['message'] ?? 'Failed load parents'];
        }

        $json = $res['data'] ?? null;
        $payload = data_get($json, 'data.data') ?? data_get($json, 'data') ?? [];

        $items = $payload;
        if (is_array($payload) && array_key_exists('data', $payload)) {
            $items = $payload['data'] ?? [];
        }
        $items = is_array($items) ? $items : [];

        $parents = collect($items)
            ->map(function ($a) {
                return [
                    'id' => data_get($a, 'id'),
                    'code' => (string) data_get($a, 'code', ''),
                    'name' => (string) data_get($a, 'name', ''),
                    'type' => (string) data_get($a, 'type', ''),
                    'is_postable' => (bool) data_get($a, 'is_postable', true),
                ];
            })
            ->filter(fn($a) => !empty($a['id']))
            ->values()
            ->all();

        return [$parents, null];
    }

    public function index(Request $request)
    {
        $q = (string) $request->query('q', '');
        $page = (int) $request->query('page', 1);
        $limit = (int) $request->query('limit', 20);

        $res = FinanceApiHelper::get('/v1/accounts', [
            'q' => $q,
            'page' => $page,
            'limit' => $limit,
        ]);

        $payload = $this->unwrapData($res['data'] ?? null) ?? [];
        $items = data_get($payload, 'data', $payload);
        $items = is_array($items) ? $items : [];

        $meta = data_get($payload, 'meta');
        if (!is_array($meta)) {
            $meta = ['total' => count($items), 'page' => $page, 'limit' => $limit];
        }

        // Prefer backend meta for pagination truth.
        $page = (int) data_get($meta, 'page', $page);
        $limit = (int) data_get($meta, 'limit', $limit);
        $total = data_get($meta, 'total');
        $totalPages = data_get($meta, 'totalPages');

        if ($total === null && $totalPages !== null) {
            $total = (int) $totalPages * (int) $limit;
        }
        $total = (int) ($total ?? count($items));

        // map parent_id => "CODE - NAME" supaya index bisa tampil parent yang readable
        [$allAccounts, $parentsErr] = $this->fetchParents();

        $parentMap = collect($allAccounts)->mapWithKeys(function ($a) {
            return [$a['id'] => trim(($a['code'] ?? '') . ' - ' . ($a['name'] ?? ''))];
        })->all();

        $paginator = new LengthAwarePaginator(
            $items,
            $total,
            $limit,
            $page,
            [
                'path' => route('finance.accounts.index'),
                'query' => $request->query(),
            ]
        );

        return view('finance.accounts.index', [
            'accounts' => $paginator,
            'q' => $q,
            'limit' => $limit,
            'meta' => $meta,
            'parentMap' => $parentMap,
            'apiError' => ($res['success'] ?? false) ? null : ($res['message'] ?? 'Failed'),
            // kalau mau tampilkan error parents, bisa digabungkan
            'parentsError' => $parentsErr,
        ]);
    }

    public function create()
    {
        [$parents, $parentsError] = $this->fetchParents();

        return view('finance.accounts.create', [
            'parents' => $parents,
            'parentsError' => $parentsError,
        ]);
    }

    public function store(Request $request)
    {
        $payload = $request->validate([
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:200',
            'type' => 'required|in:asset,liability,equity,revenue,expense',

            'parent_id' => 'nullable|string|max:50',
            'is_postable' => 'required|in:0,1',
            'is_active' => 'required|in:0,1',
            'requires_bp' => 'required|in:0,1',
            'subledger' => 'nullable|string|max:50',
            'cf_activity' => 'nullable|in:cash,operating,investing,financing',
            'pl_category' => 'nullable|in:revenue,cogs,opex,depreciation_amortization,non_operating,other',
        ]);

        // rapikan empty string -> null
        $payload['parent_id'] = ($payload['parent_id'] ?? null) ?: null;
        $payload['subledger'] = ($payload['subledger'] ?? null) ?: null;
        $payload['cf_activity'] = ($payload['cf_activity'] ?? null) ?: null;
        $payload['pl_category'] = ($payload['pl_category'] ?? null) ?: null;

        $payload = [
            'code' => (string) $payload['code'],
            'name' => (string) $payload['name'],
            'type' => (string) $payload['type'],
            'parent_id' => $payload['parent_id'],
            'is_postable' => ((string) $payload['is_postable'] === '1'),
            'is_active' => ((string) $payload['is_active'] === '1'),
            'requires_bp' => ((string) $payload['requires_bp'] === '1'),
            'subledger' => $payload['subledger'],
            'cf_activity' => $payload['cf_activity'],
            'pl_category' => $payload['pl_category'],
        ];

        $res = FinanceApiHelper::post('/v1/accounts', $payload);

        if (!($res['success'] ?? false)) {
            if (($res['error_code'] ?? null) === 'ACCOUNT_SOFT_DELETED') {
                $candidate = $this->unwrapData($res['data'] ?? null) ?? [];
                return back()
                    ->withErrors(['api' => $res['message'] ?? 'Gagal'])
                    ->withInput()
                    ->with('restoreCandidate', $candidate);
            }

            return back()->withErrors(['api' => $res['message'] ?? 'Gagal'])->withInput();
        }

        return redirect()->route('finance.accounts.index')->with('success', 'Account dibuat');
    }

    public function restore(string $id)
    {
        $res = FinanceApiHelper::post("/v1/accounts/{$id}/restore");

        if (!($res['success'] ?? false)) {
            return back()->withErrors(['api' => $res['message'] ?? 'Gagal restore']);
        }

        return redirect()
            ->route('finance.accounts.edit', $id)
            ->with('success', 'Account berhasil direstore. Silakan cek/ubah datanya.');
    }

    public function edit(string $id)
    {
        $res = FinanceApiHelper::get("/v1/accounts/{$id}");
        abort_if(!($res['success'] ?? false), 500, $res['message'] ?? 'Failed');

        $account = $this->unwrapData($res['data'] ?? null);
        abort_if(!is_array($account) || empty(data_get($account, 'id')), 404);

        // parent candidates
        [$parents, $parentsError] = $this->fetchParents();

        // jangan boleh pilih dirinya sendiri sebagai parent
        $parents = collect($parents)->reject(fn($p) => $p['id'] === $id)->values()->all();

        return view('finance.accounts.edit', compact('account', 'parents', 'parentsError'));
    }

    public function update(string $id, Request $request)
    {
        $payload = $request->validate([
            'name' => 'required|string|max:200',
            'parent_id' => 'nullable|string|max:50',
            'is_postable' => 'required|in:0,1',
            'is_active' => 'required|in:0,1',
            'requires_bp' => 'required|in:0,1',
            'subledger' => 'nullable|string|max:50',
            'cf_activity' => 'nullable|in:cash,operating,investing,financing',
            'pl_category' => 'nullable|in:revenue,cogs,opex,depreciation_amortization,non_operating,other',
        ]);

        $payload['parent_id'] = ($payload['parent_id'] ?? null) ?: null;
        $payload['subledger'] = ($payload['subledger'] ?? null) ?: null;
        $payload['cf_activity'] = ($payload['cf_activity'] ?? null) ?: null;
        $payload['pl_category'] = ($payload['pl_category'] ?? null) ?: null;

        $payload = [
            'name' => (string) $payload['name'],
            'parent_id' => $payload['parent_id'],
            'is_postable' => ((string) $payload['is_postable'] === '1'),
            'is_active' => ((string) $payload['is_active'] === '1'),
            'requires_bp' => ((string) $payload['requires_bp'] === '1'),
            'subledger' => $payload['subledger'],
            'cf_activity' => $payload['cf_activity'],
            'pl_category' => $payload['pl_category'],
        ];

        $res = FinanceApiHelper::put("/v1/accounts/{$id}", $payload);

        if (!($res['success'] ?? false)) {
            return back()->withErrors(['api' => $res['message'] ?? 'Gagal'])->withInput();
        }

        return redirect()->route('finance.accounts.index')->with('success', 'Account diupdate');
    }

    public function destroy(string $id)
    {
        $res = FinanceApiHelper::delete("/v1/accounts/{$id}");

        if (!($res['success'] ?? false)) {
            return back()->withErrors(['api' => $res['message'] ?? 'Gagal delete']);
        }

        return back()->with('success', 'Account deleted');
    }
}

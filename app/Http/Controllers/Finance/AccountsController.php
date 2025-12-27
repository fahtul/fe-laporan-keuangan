<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Helpers\FinanceApiHelper;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class AccountsController extends Controller
{
    private function fetchParents()
    {
        // ambil banyak untuk dropdown parent
        $res = FinanceApiHelper::get('/v1/accounts', [
            'page' => 1,
            'limit' => 100,
        ]);

        if (!($res['success'] ?? false)) {
            return [[], $res['message'] ?? 'Failed load parents'];
        }

        $parents = collect($res['data']['data'] ?? [])
            ->map(function ($a) {
                return [
                    'id' => $a['id'] ?? null,
                    'code' => $a['code'] ?? '',
                    'name' => $a['name'] ?? '',
                    'type' => $a['type'] ?? '',
                    'is_postable' => (bool)($a['is_postable'] ?? true),
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

        $items = $res['data']['data'] ?? [];
        $meta  = $res['data']['meta'] ?? ['total' => 0, 'page' => $page, 'limit' => $limit];

        // map parent_id => "CODE - NAME" supaya index bisa tampil parent yang readable
        [$allAccounts, $parentsErr] = $this->fetchParents();

        $parentMap = collect($allAccounts)->mapWithKeys(function ($a) {
            return [$a['id'] => trim(($a['code'] ?? '') . ' - ' . ($a['name'] ?? ''))];
        })->all();

        $paginator = new LengthAwarePaginator(
            $items,
            (int) ($meta['total'] ?? 0),
            (int) ($meta['limit'] ?? $limit),
            (int) ($meta['page'] ?? $page),
            [
                'path' => route('finance.accounts.index'),
                'query' => $request->query(),
            ]
        );

        return view('finance.accounts.index', [
            'accounts' => $paginator,
            'q' => $q,
            'limit' => $limit,
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
            'cf_activity' => 'nullable|in:cash,operating,investing,financing',

            // baru:
            'parent_id' => 'nullable|string|max:50',
            'is_postable' => 'required', // karena di form kita kirim hidden 0 + checkbox 1
        ]);

        // rapikan empty string -> null
        $payload['parent_id'] = ($payload['parent_id'] ?? null) ?: null;
        $payload['cf_activity'] = ($payload['cf_activity'] ?? null) ?: null;

        $payload['is_postable'] = ((string) ($payload['is_postable'] ?? '0') === '1');
        $res = FinanceApiHelper::post('/v1/accounts', $payload);

        if (!($res['success'] ?? false)) {
            if (($res['error_code'] ?? null) === 'ACCOUNT_SOFT_DELETED') {
                return back()
                    ->withErrors(['api' => $res['message'] ?? 'Gagal'])
                    ->withInput()
                    ->with('restoreCandidate', data_get($res, 'data.data') ?? data_get($res, 'data'));
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
        // sementara: ambil list lalu cari id (sesuai kode kamu)
        $res = FinanceApiHelper::get('/v1/accounts', ['page' => 1, 'limit' => 100]);
        if (!($res['success'] ?? false)) abort(500, $res['message'] ?? 'Failed');

        $account = collect($res['data']['data'] ?? [])->firstWhere('id', $id);
        abort_if(!$account, 404);

        // parent candidates
        [$parents, $parentsError] = $this->fetchParents();

        // jangan boleh pilih dirinya sendiri sebagai parent
        $parents = collect($parents)->reject(fn($p) => $p['id'] === $id)->values()->all();

        // dd($account, $parents);

        return view('finance.accounts.edit', compact('account', 'parents', 'parentsError'));
    }

    public function update(string $id, Request $request)
    {
        $payload = $request->validate([
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:200',
            'type' => 'required|in:asset,liability,equity,revenue,expense',
            'cf_activity' => 'nullable|in:cash,operating,investing,financing',

            // baru:
            'parent_id' => 'nullable|string|max:50',
            'is_postable' => 'required',
        ]);

        $payload['parent_id'] = ($payload['parent_id'] ?? null) ?: null;
        $payload['cf_activity'] = ($payload['cf_activity'] ?? null) ?: null;
        $payload['is_postable'] = ((string)($payload['is_postable'] ?? '0') === '1');


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

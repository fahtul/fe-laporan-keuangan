<?php

namespace App\Http\Controllers\Finance;

use App\Helpers\FinanceApiHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AccountsCashflowMappingController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $res = FinanceApiHelper::get('/v1/accounts', [
            'q' => $q,
            'page' => 1,
            'limit' => 100,
        ]);

        $apiError = null;
        $items = [];

        if (!($res['success'] ?? false)) {
            $apiError = $res['message'] ?? 'Failed to load accounts';
        } else {
            $json = $res['data'] ?? null;
            $payload = data_get($json, 'data.data');
            $items = is_array($payload) ? $payload : [];
        }

        $accounts = collect($items)
            ->map(function ($a) {
                return [
                    'id' => (string) ($a['id'] ?? ''),
                    'code' => (string) ($a['code'] ?? ''),
                    'name' => (string) ($a['name'] ?? ''),
                    'type' => (string) ($a['type'] ?? ''),
                    'is_postable' => (bool) ($a['is_postable'] ?? true),
                    'parent_id' => $a['parent_id'] ?? null,
                    'cf_activity' => $a['cf_activity'] ?? null,
                ];
            })
            ->filter(fn($a) => $a['id'] !== '')
            ->values()
            ->all();

        return view('finance.accounts.cashflow_mapping', [
            'q' => $q,
            'accounts' => $accounts,
            'apiError' => $apiError,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'account_id' => 'required|array',
            'account_id.*' => 'required|string',
            'cf_activity' => 'required|array',
            'cf_activity.*' => 'nullable|in:cash,operating,investing,financing',
        ]);

        $accountIds = $data['account_id'] ?? [];
        $activities = $data['cf_activity'] ?? [];

        $count = min(count($accountIds), count($activities));
        if ($count === 0) {
            return back()->withErrors(['api' => 'Tidak ada data untuk disimpan.']);
        }

        for ($i = 0; $i < $count; $i++) {
            $id = (string) ($accountIds[$i] ?? '');
            $val = $activities[$i] ?? null;

            if ($id === '') {
                continue;
            }

            $payload = [
                'cf_activity' => ($val === '' || $val === null) ? null : (string) $val,
            ];

            $res = FinanceApiHelper::put("/v1/accounts/{$id}", $payload);
            if (!($res['success'] ?? false)) {
                return back()
                    ->withErrors(['api' => "Gagal update account {$id}: " . ($res['message'] ?? 'Failed')])
                    ->withInput();
            }
        }

        return back()->with('success', 'Cash flow mapping berhasil disimpan.');
    }
}

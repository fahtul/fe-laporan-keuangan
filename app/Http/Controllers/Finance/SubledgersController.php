<?php

namespace App\Http\Controllers\Finance;

use App\Helpers\FinanceApiHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SubledgersController extends Controller
{
    private function fetchSubledgerAccounts(): array
    {
        $res = FinanceApiHelper::get('/v1/accounts/options', [
            'q' => '',
            'limit' => 500,
            'include_inactive' => 'false',
        ]);

        if (!($res['success'] ?? false)) {
            return [[], $res['message'] ?? 'Failed to load accounts'];
        }

        $json = $res['data'] ?? null;
        $payload = data_get($json, 'data.data') ?? data_get($json, 'data') ?? [];

        $items = $payload;
        if (is_array($payload) && array_key_exists('data', $payload)) {
            $items = $payload['data'] ?? [];
        }
        $items = is_array($items) ? $items : [];

        $hasRequiresBp = collect($items)->contains(function ($a) {
            return is_array($a) && array_key_exists('requires_bp', $a);
        });

        $accounts = collect($items)
            ->map(function ($a) {
                return [
                    'id' => data_get($a, 'id'),
                    'code' => (string) data_get($a, 'code', ''),
                    'name' => (string) data_get($a, 'name', ''),
                    'type' => (string) data_get($a, 'type', ''),
                    'is_postable' => (bool) data_get($a, 'is_postable', true),
                    'requires_bp' => (bool) data_get($a, 'requires_bp', false),
                ];
            })
            ->filter(function ($a) use ($hasRequiresBp) {
                if (empty($a['id']) || $a['is_postable'] !== true) {
                    return false;
                }

                if ($hasRequiresBp) {
                    return $a['requires_bp'] === true;
                }

                // Fallback: only asset/liability accounts (common AR/AP families)
                return in_array($a['type'], ['asset', 'liability'], true);
            })
            ->values()
            ->all();

        return [$accounts, null];
    }

    public function index(Request $request)
    {
        [$accounts, $accountsError] = $this->fetchSubledgerAccounts();

        $year = (string) $request->query('year', now()->format('Y'));
        if (!preg_match('/^\d{4}$/', $year)) {
            $year = now()->format('Y');
        }

        $defaultFrom = $year . '-01-01';
        $defaultTo = $year . '-12-31';

        $preset = (string) $request->query('preset', '');
        if ($preset === 'year') {
            $fromDate = $defaultFrom;
            $toDate = $defaultTo;
        } elseif ($preset === 'month') {
            $fromDate = now()->startOfMonth()->toDateString();
            $toDate = now()->endOfMonth()->toDateString();
        } else {
            $fromDate = (string) $request->query('from_date', $defaultFrom);
            $toDate = (string) $request->query('to_date', $defaultTo);
        }

        $accountId = (string) $request->query('account_id', '');
        $q = (string) $request->query('q', '');
        $includeZero = $request->boolean('include_zero');

        $page = max(1, (int) $request->query('page', 1));
        $limit = (int) $request->query('limit', 50);
        if (!in_array($limit, [20, 50, 100], true)) {
            $limit = 50;
        }

        $items = [];
        $meta = [
            'total' => 0,
            'page' => $page,
            'limit' => $limit,
        ];
        $totals = [];
        $account = null;
        $period = [
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ];
        $apiError = null;

        if ($accountId !== '') {
            $res = FinanceApiHelper::get('/v1/subledgers', [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'account_id' => $accountId,
                'q' => $q,
                'include_zero' => $includeZero ? '1' : '0',
                'page' => (string) $page,
                'limit' => (string) $limit,
            ]);

            if (!($res['success'] ?? false)) {
                $apiError = $res['message'] ?? 'Failed to load subledgers';
            } else {
                $json = $res['data'] ?? null;
                $payload = data_get($json, 'data.data') ?? data_get($json, 'data') ?? null;

                $items = data_get($payload, 'items', []) ?: [];
                $meta = data_get($payload, 'meta', $meta) ?: $meta;
                $totals = data_get($payload, 'totals', []) ?: [];
                $account = data_get($payload, 'account', null);
                $period = data_get($payload, 'period', $period) ?: $period;
            }
        }

        $selectedAccount = collect($accounts)->firstWhere('id', $accountId);
        if (is_array($account)) {
            $selectedAccount = array_merge($selectedAccount ?? [], [
                'id' => data_get($account, 'id', $accountId),
                'code' => (string) data_get($account, 'code', data_get($selectedAccount, 'code', '')),
                'name' => (string) data_get($account, 'name', data_get($selectedAccount, 'name', '')),
                'type' => (string) data_get($account, 'type', data_get($selectedAccount, 'type', '')),
            ]);
        }

        $total = (int) data_get($meta, 'total', 0);
        $meta['page'] = (int) data_get($meta, 'page', $page);
        $meta['limit'] = (int) data_get($meta, 'limit', $limit);
        $meta['total_pages'] = $meta['limit'] > 0 ? (int) ceil($total / $meta['limit']) : 1;

        return view('finance.subledgers.index', [
            'year' => $year,
            'preset' => $preset,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'accountId' => $accountId,
            'q' => $q,
            'includeZero' => $includeZero,
            'page' => $page,
            'limit' => $limit,

            'accounts' => $accounts,
            'accountsError' => $accountsError,
            'selectedAccount' => $selectedAccount,

            'items' => $items,
            'meta' => $meta,
            'totals' => $totals,
            'period' => $period,
            'apiError' => $apiError,
        ]);
    }

    public function show(Request $request, string $bpId)
    {
        $validated = $request->validate([
            'account_id' => 'required|string',
            'from_date' => 'required|date',
            'to_date' => 'required|date',
            'year' => 'nullable|string',
            'q' => 'nullable|string',
            'include_zero' => 'nullable|string',
            'page' => 'nullable|string',
            'limit' => 'nullable|string',
        ]);

        $year = (string) ($validated['year'] ?? now()->format('Y'));
        if (!preg_match('/^\d{4}$/', $year)) {
            $year = now()->format('Y');
        }

        $accountId = (string) $validated['account_id'];
        $fromDate = (string) $validated['from_date'];
        $toDate = (string) $validated['to_date'];

        $res = FinanceApiHelper::get("/v1/subledgers/{$bpId}", [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'account_id' => $accountId,
        ]);

        $apiError = null;
        $rows = [];
        $opening = null;
        $closing = null;
        $totals = [];
        $account = null;
        $bp = null;
        $period = [
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ];

        if (!($res['success'] ?? false)) {
            $apiError = $res['message'] ?? 'Failed to load subledger';
        } else {
            $json = $res['data'] ?? null;
            $payload = data_get($json, 'data.data') ?? data_get($json, 'data') ?? null;

            $rows = data_get($payload, 'rows', []) ?: [];
            $opening = data_get($payload, 'opening', null);
            $closing = data_get($payload, 'closing', null);
            $totals = data_get($payload, 'totals', []) ?: [];
            $account = data_get($payload, 'account', null);
            $bp = data_get($payload, 'bp', null);
            $period = data_get($payload, 'period', $period) ?: $period;
        }

        $backUrl = route('finance.subledgers.index', array_filter([
            'year' => $year,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'account_id' => $accountId,
            'q' => (string) ($validated['q'] ?? ''),
            'include_zero' => (string) ($validated['include_zero'] ?? '0'),
            'page' => (string) ($validated['page'] ?? '1'),
            'limit' => (string) ($validated['limit'] ?? '50'),
        ], fn ($v) => $v !== ''));

        return view('finance.subledgers.show', [
            'year' => $year,
            'bpId' => $bpId,
            'accountId' => $accountId,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'period' => $period,
            'rows' => $rows,
            'opening' => $opening,
            'closing' => $closing,
            'totals' => $totals,
            'account' => $account,
            'bp' => $bp,
            'apiError' => $apiError,
            'backUrl' => $backUrl,
        ]);
    }
}


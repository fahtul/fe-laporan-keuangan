<?php

namespace App\Http\Controllers\Finance;

use App\Helpers\FinanceApiHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CashFlowController extends Controller
{
    public function index(Request $request)
    {
        $year = (string) $request->query('year', now()->format('Y'));
        if (!preg_match('/^\d{4}$/', $year)) {
            $year = now()->format('Y');
        }

        $defaultFrom = $year . '-01-01';
        $defaultTo = $year . '-12-31';

        $includeZero = ((string) $request->query('include_zero', '0') === '1');
        $includeDetails = ((string) $request->query('include_details', '1') === '1');

        $cashPrefix = trim((string) $request->query('cash_prefix', '11'));
        if ($cashPrefix === '') {
            $cashPrefix = '11';
        }

        $preset = (string) $request->query('preset', '');
        if ($preset === 'year') {
            $fromDate = $defaultFrom;
            $toDate = $defaultTo;
        } elseif ($preset === 'month') {
            $month = (int) now()->format('n');
            $start = Carbon::createFromDate((int) $year, $month, 1)->startOfMonth();
            $end = (clone $start)->endOfMonth();
            $fromDate = $start->toDateString();
            $toDate = $end->toDateString();
        } else {
            $fromDate = (string) $request->query('from_date', $defaultFrom);
            $toDate = (string) $request->query('to_date', $defaultTo);
        }

        try {
            $fromDate = Carbon::createFromFormat('Y-m-d', $fromDate)->toDateString();
        } catch (\Throwable $e) {
            $fromDate = $defaultFrom;
        }

        try {
            $toDate = Carbon::createFromFormat('Y-m-d', $toDate)->toDateString();
        } catch (\Throwable $e) {
            $toDate = $defaultTo;
        }

        if (Carbon::parse($fromDate)->gt(Carbon::parse($toDate))) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $cashAccountIdsRaw = $request->query('cash_account_ids');
        $cashAccountIds = [];
        if (is_array($cashAccountIdsRaw)) {
            $cashAccountIds = $cashAccountIdsRaw;
        } else {
            $csv = trim((string) $cashAccountIdsRaw);
            if ($csv !== '') {
                $cashAccountIds = preg_split('/[\s,]+/', $csv) ?: [];
            }
        }
        $cashAccountIds = collect($cashAccountIds)
            ->map(fn($v) => trim((string) $v))
            ->filter(fn($v) => $v !== '')
            ->values()
            ->all();

        $apiQuery = [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'include_zero' => $includeZero ? '1' : '0',
            'include_details' => $includeDetails ? '1' : '0',
            'cash_prefix' => $cashPrefix,
        ];

        if (!empty($cashAccountIds)) {
            $apiQuery['cash_account_ids'] = $cashAccountIds;
        }

        $res = FinanceApiHelper::get('/v1/cash-flow', $apiQuery);
        // dd($res);
        $apiError = null;
        $period = [
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ];

        $cashAccounts = [];
        $cash = [];
        $activities = [];
        $totals = [];
        $reconciliation = [];

        if (!($res['success'] ?? false)) {
            $apiError = $res['message'] ?? 'Failed to load cash flow';
        } else {
            $json = $res['data'] ?? null;
            $payload = data_get($json, 'data.data') ?? data_get($json, 'data') ?? null;

            $period = data_get($payload, 'period', $period) ?: $period;
            $cashAccounts = data_get($payload, 'cash_accounts', []) ?: [];
            $cash = data_get($payload, 'cash', []) ?: [];
            $activities = data_get($payload, 'activities', []) ?: [];
            $totals = data_get($payload, 'totals', []) ?: [];
            $reconciliation = data_get($payload, 'reconciliation', []) ?: [];
        }

        return view('finance.cash_flow.index', [
            'year' => $year,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'includeZero' => $includeZero,
            'includeDetails' => $includeDetails,
            'cashPrefix' => $cashPrefix,
            'cashAccountIds' => $cashAccountIds,
            'period' => $period,
            'cashAccounts' => $cashAccounts,
            'cash' => $cash,
            'activities' => $activities,
            'totals' => $totals,
            'reconciliation' => $reconciliation,
            'apiError' => $apiError,
        ]);
    }
}

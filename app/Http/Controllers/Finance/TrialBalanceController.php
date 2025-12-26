<?php

namespace App\Http\Controllers\Finance;

use App\Helpers\FinanceApiHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TrialBalanceController extends Controller
{
    public function index(Request $request)
    {
        $year = (string) $request->query('year', now()->format('Y'));
        if (!preg_match('/^\d{4}$/', $year)) {
            $year = now()->format('Y');
        }

        $defaultFrom = $year . '-01-01';
        $defaultTo   = $year . '-12-31';

        // ✅ untuk GET checkbox: baca tegas '1'
        $includeZero   = ((string) $request->query('include_zero', '0') === '1');
        $includeHeader = ((string) $request->query('include_header', '0') === '1');

        $preset = (string) $request->query('preset', '');

        if ($preset === 'year') {
            $fromDate = $defaultFrom;
            $toDate   = $defaultTo;
       } elseif ($preset === 'month') {
            $base = Carbon::createFromDate((int)$year, now()->month, 1);
            $fromDate = $base->startOfMonth()->toDateString();
            $toDate   = $base->endOfMonth()->toDateString();
        } else {
            $fromDate = (string) $request->query('from_date', $defaultFrom);
            $toDate   = (string) $request->query('to_date', $defaultTo);
        }

        // ✅ validasi ringan format tanggal, kalau invalid fallback ke default
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

        // ✅ pastikan from <= to
        if (Carbon::parse($fromDate)->gt(Carbon::parse($toDate))) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $res = FinanceApiHelper::get('/v1/trial-balance', [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'include_zero' => $includeZero ? '1' : '0',
            'include_header' => $includeHeader ? '1' : '0',
        ]);

        $apiError = null;
        $items = [];
        $totals = [];
        $period = [
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ];

        if (!($res['success'] ?? false)) {
            $apiError = $res['message'] ?? 'Failed to load trial balance';
        } else {
            // FinanceApiHelper::send => 'data' berisi JSON full dari backend
            $json = $res['data'] ?? null;

            // backend ideal: { status, data: { period, items, totals } }
            $payload = data_get($json, 'data');

            $items  = data_get($payload, 'items', []) ?: [];
            $totals = data_get($payload, 'totals', []) ?: [];
            $period = data_get($payload, 'period', $period) ?: $period;
        }

        return view('finance.trial_balance.index', [
            'year' => $year,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'includeZero' => $includeZero,
            'includeHeader' => $includeHeader,
            'items' => $items,
            'totals' => $totals,
            'period' => $period,
            'apiError' => $apiError,
        ]);
    }
}

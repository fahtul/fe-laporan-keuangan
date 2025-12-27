<?php

namespace App\Http\Controllers\Finance;

use App\Helpers\FinanceApiHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class WorksheetController extends Controller
{
    public function index(Request $request)
    {
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

        $includeZero = ((string) $request->query('include_zero', '0') === '1');
        $includeHeader = ((string) $request->query('include_header', '0') === '1');
        $includeVirtualProfit = ((string) $request->query('include_virtual_profit', '0') === '1');
        $useCodeRule = ((string) $request->query('use_code_rule', '0') === '1');

        $res = FinanceApiHelper::get('/v1/worksheets', [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'include_zero' => $includeZero ? '1' : '0',
            'include_header' => $includeHeader ? '1' : '0',
            'include_virtual_profit' => $includeVirtualProfit ? '1' : '0',
            'use_code_rule' => $useCodeRule ? '1' : '0',
        ]);

        $apiError = null;
        $items = [];
        $totals = [];
        $period = [
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ];
        $virtualRows = [];

        if (!($res['success'] ?? false)) {
            $apiError = $res['message'] ?? 'Failed to load worksheet';
        } else {
            $json = $res['data'] ?? null;
            $payload = data_get($json, 'data.data') ?? data_get($json, 'data') ?? null;

            $items = data_get($payload, 'items', []) ?: [];
            $totals = data_get($payload, 'totals', []) ?: [];
            $period = data_get($payload, 'period', $period) ?: $period;
            $virtualRows = data_get($payload, 'virtual_rows', []) ?: [];
        }

        return view('finance.worksheet.index', [
            'year' => $year,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'includeZero' => $includeZero,
            'includeHeader' => $includeHeader,
            'includeVirtualProfit' => $includeVirtualProfit,
            'useCodeRule' => $useCodeRule,
            'items' => $items,
            'totals' => $totals,
            'period' => $period,
            'virtualRows' => $virtualRows,
            'apiError' => $apiError,
        ]);
    }
}


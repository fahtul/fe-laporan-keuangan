<?php

namespace App\Http\Controllers\Finance;

use App\Helpers\FinanceApiHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class EquityStatementController extends Controller
{
    public function index(Request $request)
    {
        $year = (string) $request->query('year', now()->format('Y'));
        if (!preg_match('/^\d{4}$/', $year)) {
            $year = now()->format('Y');
        }

        $defaultFrom = "{$year}-01-01";
        $defaultTo = "{$year}-12-31";

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

        $includeZero = $request->boolean('include_zero');
        $includeHeader = $request->boolean('include_header');
        $useCodeRule = $request->boolean('use_code_rule');
        $includeVirtualProfit = $request->boolean('include_virtual_profit', true);

        $profitModeRaw = (string) $request->query('profit_mode', 'net');
        $profitMode = in_array($profitModeRaw, ['net', 'after_tax'], true) ? $profitModeRaw : 'net';

        $res = FinanceApiHelper::get('/v1/equity-statement', [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'include_zero' => $includeZero ? '1' : '0',
            'include_header' => $includeHeader ? '1' : '0',
            'use_code_rule' => $useCodeRule ? '1' : '0',
            'include_virtual_profit' => $includeVirtualProfit ? '1' : '0',
            'profit_mode' => $profitMode,
        ]);

        $json = $res['data'] ?? null;
        $payload = data_get($json, 'data.data') ?? data_get($json, 'data') ?? null;

        $opening = data_get($payload, 'opening', []) ?: [];
        $movements = data_get($payload, 'movements', []) ?: [];
        $closing = data_get($payload, 'closing', []) ?: [];
        $period = data_get($payload, 'period', [
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ]) ?: [
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ];
        $totals = data_get($payload, 'totals', []) ?: [];
        $meta = data_get($payload, 'meta', []) ?: [];

        $apiError = !($res['success'] ?? false) ? ($res['message'] ?? 'Failed') : null;

        return view('finance.equity_statement.index', [
            'year' => $year,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'includeZero' => $includeZero,
            'includeHeader' => $includeHeader,
            'useCodeRule' => $useCodeRule,
            'includeVirtualProfit' => $includeVirtualProfit,
            'profitMode' => $profitMode,
            'opening' => $opening,
            'movements' => $movements,
            'closing' => $closing,
            'totals' => $totals,
            'period' => $period,
            'meta' => $meta,
            'apiError' => $apiError,
        ]);
    }
}


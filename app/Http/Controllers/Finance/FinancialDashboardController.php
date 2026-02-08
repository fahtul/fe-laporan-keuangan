<?php

namespace App\Http\Controllers\Finance;

use App\Helpers\FinanceApiHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FinancialDashboardController extends Controller
{
    private function isDateYmd(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    }

    private function unwrapJsonData(array $res)
    {
        $json = $res['data'] ?? null;
        if (!is_array($json)) return null;

        return data_get($json, 'data.data') ?? data_get($json, 'data') ?? null;
    }

    public function index(Request $request)
    {
        $defaultFrom = now()->startOfYear()->toDateString();
        $defaultTo = now()->endOfYear()->toDateString();

        $fromDate = (string) $request->query('from_date', $defaultFrom);
        $toDate = (string) $request->query('to_date', $defaultTo);
        $interval = (string) $request->query('interval', 'month');
        $chartType = (string) $request->query('chart_type', 'line');

        if (!$this->isDateYmd($fromDate)) $fromDate = $defaultFrom;
        if (!$this->isDateYmd($toDate)) $toDate = $defaultTo;

        $allowedIntervals = ['month', 'quarter', 'year'];
        if (!in_array($interval, $allowedIntervals, true)) $interval = 'month';

        $allowedChartTypes = ['line', 'bar', 'stacked_bar', 'doughnut'];
        if (!in_array($chartType, $allowedChartTypes, true)) $chartType = 'line';

        $res = FinanceApiHelper::get('/v1/charts/financials', [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'interval' => $interval,
        ]);

        $apiError = null;
        $payload = null;
        $period = [
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ];

        if (!($res['success'] ?? false)) {
            $apiError = (string) ($res['message'] ?? 'Failed to load financial charts');
        } else {
            $payload = $this->unwrapJsonData($res);
            $payload = is_array($payload) ? $payload : null;

            $period = is_array($payload) ? (data_get($payload, 'period', $period) ?: $period) : $period;
        }

        return view('finance.financial_dashboard.index', [
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'interval' => $interval,
            'chartType' => $chartType,
            'period' => $period,
            'payload' => $payload,
            'apiError' => $apiError,
            'allowedIntervals' => $allowedIntervals,
            'allowedChartTypes' => $allowedChartTypes,
        ]);
    }
}

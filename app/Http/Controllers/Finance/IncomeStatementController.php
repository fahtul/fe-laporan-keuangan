<?php

namespace App\Http\Controllers\Finance;

use App\Helpers\FinanceApiHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class IncomeStatementController extends Controller
{
    public function index(Request $request)
    {
        $year = (string) $request->query('year', now()->format('Y'));
        if (!preg_match('/^\d{4}$/', $year)) {
            $year = now()->format('Y');
        }

        $defaultFrom = $year . '-01-01';
        $defaultTo = $year . '-12-31';

        $includeZero = $request->boolean('include_zero');
        $includeHeader = $request->boolean('include_header');

        $applyTax = $request->boolean('apply_tax');
        $taxRateRaw = trim((string) $request->query('tax_rate', ''));

        $taxRate = null;
        if ($applyTax) {
            $taxRate = $taxRateRaw !== '' ? (float) $taxRateRaw : 0.11;
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

        $apiQuery = [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'include_zero' => $includeZero ? '1' : '0',
            'include_header' => $includeHeader ? '1' : '0',
        ];

        if ($taxRate !== null) {
            $apiQuery['tax_rate'] = (string) $taxRate;
        }

        $res = FinanceApiHelper::get('/v1/income-statement', $apiQuery);
        // dd( $apiQuery, $res);
        $apiError = null;
        $sections = [];
        $summary = [];
        $period = [
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ];

        if (!($res['success'] ?? false)) {
            $apiError = $res['message'] ?? 'Failed to load income statement';
        } else {
            $json = $res['data'] ?? null;
            $payload = data_get($json, 'data.data') ?? data_get($json, 'data') ?? null;

            $period = data_get($payload, 'period', $period) ?: $period;
            $sections = data_get($payload, 'sections', []) ?: [];
            $summary = data_get($payload, 'summary', []) ?: [];
        }

        return view('finance.income_statement.index', [
            'year' => $year,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'includeZero' => $includeZero,
            'includeHeader' => $includeHeader,
            'applyTax' => $applyTax,
            'taxRate' => $taxRate,
            'period' => $period,
            'sections' => $sections,
            'summary' => $summary,
            'apiError' => $apiError,
        ]);
    }
}


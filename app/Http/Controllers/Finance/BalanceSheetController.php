<?php

namespace App\Http\Controllers\Finance;

use App\Helpers\FinanceApiHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BalanceSheetController extends Controller
{
    public function index(Request $request)
    {
        $year = (string) $request->query('year', now()->format('Y'));
        if (!preg_match('/^\d{4}$/', $year)) {
            $year = now()->format('Y');
        }

        $defaultAsOf = $year . '-12-31';

        $includeZero = ((string) $request->query('include_zero', '0') === '1');
        $includeHeader = ((string) $request->query('include_header', '0') === '1');

        $profitBasis = (string) $request->query('profit_basis', 'after_tax');
        if (!in_array($profitBasis, ['after_tax', 'operating', 'net'], true)) {
            $profitBasis = 'after_tax';
        }

        $preset = (string) $request->query('preset', '');
        if ($preset === 'year') {
            $asOf = $defaultAsOf;
        } elseif ($preset === 'month') {
            $month = (int) now()->format('n');
            $asOf = Carbon::createFromDate((int) $year, $month, 1)->endOfMonth()->toDateString();
        } else {
            $asOf = (string) $request->query('as_of', $defaultAsOf);
        }

        try {
            $asOf = Carbon::createFromFormat('Y-m-d', $asOf)->toDateString();
        } catch (\Throwable $e) {
            $asOf = $defaultAsOf;
        }

        $res = FinanceApiHelper::get('/v1/balance-sheet', [
            'as_of' => $asOf,
            'year' => $year,
            'include_zero' => $includeZero ? '1' : '0',
            'include_header' => $includeHeader ? '1' : '0',
            'profit_basis' => $profitBasis,
        ]);

        $apiError = null;
        $sections = [];
        $totals = [];
        $profitPeriod = [];
        $balanced = false;
        $difference = 0.0;

        if (!($res['success'] ?? false)) {
            $apiError = $res['message'] ?? 'Failed to load balance sheet';
        } else {
            $json = $res['data'] ?? null;
            $payload = data_get($json, 'data.data') ?? data_get($json, 'data') ?? null;

            $asOf = (string) (data_get($payload, 'as_of') ?? $asOf);
            $sections = data_get($payload, 'sections', []) ?: [];
            $totals = data_get($payload, 'totals', []) ?: [];
            $profitPeriod = data_get($payload, 'profit_period', []) ?: [];
            $balanced = (bool) data_get($payload, 'balanced', false);

            $difference = (float) data_get($totals, 'difference', 0);
        }

        return view('finance.balance_sheet.index', [
            'year' => $year,
            'asOf' => $asOf,
            'includeZero' => $includeZero,
            'includeHeader' => $includeHeader,
            'profitBasis' => $profitBasis,
            'sections' => $sections,
            'totals' => $totals,
            'profitPeriod' => $profitPeriod,
            'balanced' => $balanced,
            'difference' => $difference,
            'apiError' => $apiError,
        ]);
    }
}


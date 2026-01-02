<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Helpers\FinanceApiHelper;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private function normalizePayload(array $res): array
    {
        $json = $res['data'] ?? null;
        if (!is_array($json)) return [];

        $payload = data_get($json, 'data');
        if (is_array($payload) && is_array(data_get($payload, 'data'))) {
            $payload = data_get($payload, 'data');
        }

        return is_array($payload) ? $payload : [];
    }

    private function parseNumber(mixed $val): float
    {
        if (is_int($val) || is_float($val)) return (float) $val;
        if (!is_string($val)) return 0.0;

        $s = trim($val);
        if ($s === '') return 0.0;

        // Handle Indonesian-style "55.000.000,25" and similar.
        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (str_contains($s, ',') && !str_contains($s, '.')) {
            $s = str_replace(',', '.', $s);
        } elseif (preg_match('/^\-?\d{1,3}(\.\d{3})+(\,\d+)?$/', $s)) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        }

        return (float) $s;
    }

    public function index(Request $request)
    {
        $year = (string) $request->query('year', now()->format('Y'));
        if (!preg_match('/^\d{4}$/', $year)) $year = now()->format('Y');

        $fromDate = $year . '-01-01';
        $toDate   = $year . '-12-31';

        $stats = [
            'period_status' => null,
            'cash' => null,
            'ar' => null,
            'ap' => null,
            'profit_ytd' => null,
        ];

        // 1) Trial Balance (buat Cash/AR/AP)
        $tbRes = FinanceApiHelper::get('/v1/trial-balance', [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'include_zero' => '0',
            'include_header' => '0',
        ]);

        // dd($tbRes);

        $items = [];
        if (($tbRes['success'] ?? false) === true) {
            $payload = $this->normalizePayload($tbRes);
            $items = data_get($payload, 'items', []);
            $items = is_array($items) ? $items : [];
        }

        $cash = 0.0;
        $ar = 0.0;
        $ap = 0.0;
        $profitFromTb = 0.0;

        foreach ($items as $it) {
            $code = (string) data_get($it, 'code', '');
            $type = strtolower((string) data_get($it, 'type', ''));

            $bal = $this->parseNumber(data_get($it, 'closing_balance', 0));
            $pos = strtolower((string) data_get($it, 'closing_pos', 'debit')); // debit/credit
            $isDebit = in_array($pos, ['debit', 'd'], true);
            $signed = $isDebit ? $bal : -$bal;

            // Cash: kode 11xx
            if (str_starts_with($code, '11')) $cash += $signed;

            // AR: contoh umum 12xx (piutang)
            if (str_starts_with($code, '12')) $ar += $signed;

            // AP: contoh umum 21xx (utang) -> tampilkan sebagai positif
            if (str_starts_with($code, '21')) $ap += abs($signed);

            // Profit YTD fallback dari Trial Balance: sum(-signed) untuk akun P&L
            if (in_array($type, ['revenue', 'expense'], true)) {
                $profitFromTb += (-$signed);
            }
        }

        if (count($items) > 0) {
            $stats['cash'] = $cash;
            $stats['ar'] = $ar;
            $stats['ap'] = $ap;
        }

        // 2) Profit YTD (Income Statement)
        $plRes = FinanceApiHelper::get('/v1/income-statement', [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'include_zero' => '0',
            'include_header' => '0',
        ]);

        if (($plRes['success'] ?? false) === true) {
            $payload = $this->normalizePayload($plRes);
            $summary = data_get($payload, 'summary', []);

            $profitVal =
                data_get($summary, 'net_profit_after_tax')
                ?? data_get($summary, 'net_profit')
                ?? data_get($summary, 'operating_profit')
                ?? data_get($summary, 'gross_profit');

            if ($profitVal === null) {
                $totalRevenue = data_get($summary, 'total_revenue');
                $totalCogs = data_get($summary, 'total_cogs');
                $totalOpex = data_get($summary, 'total_operating_expense');

                if ($totalRevenue !== null || $totalCogs !== null || $totalOpex !== null) {
                    $computed = $this->parseNumber($totalRevenue) - $this->parseNumber($totalCogs) - $this->parseNumber($totalOpex);
                    $taxAmount = data_get($summary, 'tax_amount');
                    if ($taxAmount !== null) {
                        $computed -= $this->parseNumber($taxAmount);
                    }
                    $profitVal = $computed;
                }
            }

            if ($profitVal !== null) {
                $stats['profit_ytd'] = $this->parseNumber($profitVal);
            }
        }

        if ($stats['profit_ytd'] === null && count($items) > 0) {
            $stats['profit_ytd'] = $profitFromTb;
        }

        // 3) Status periode (ambil dari closing status biar gak N/A)
        $closingRes = FinanceApiHelper::get('/v1/closings/year-end', ['year' => $year]);
        if (($closingRes['success'] ?? false) === true) {
            $payload = $this->normalizePayload($closingRes);
            $isClosed =
                (bool) data_get($payload, 'is_closed', false)
                || strtolower((string) data_get($payload, 'status', '')) === 'closed'
                || !empty(data_get($payload, 'closing_entry_id'));

            $stats['period_status'] = $isClosed ? 'Closed' : 'Open';
        } else {
            // fallback: kalau TB ada items, anggap Open
            $stats['period_status'] = count($items) > 0 ? 'Open' : null;
        }

        return view('dashboard', [
            'stats' => $stats,
        ]);
    }
}

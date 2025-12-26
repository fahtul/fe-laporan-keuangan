<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Helpers\FinanceApiHelper;
use Illuminate\Http\Request;

class LedgersController extends Controller
{
    private function fetchAccountOptions(): array
    {
        // ambil banyak untuk dropdown (postable saja supaya transaksi valid)
        $res = FinanceApiHelper::get('/v1/accounts', [
            'page' => 1,
            'limit' => 100,
            'q' => '',
        ]);

        if (!($res['success'] ?? false)) {
            return [[], $res['message'] ?? 'Failed to load accounts'];
        }

        $items = data_get($res, 'data.data', []);

        $accounts = collect($items)
            ->map(function ($a) {
                return [
                    'id' => $a['id'] ?? null,
                    'code' => $a['code'] ?? '',
                    'name' => $a['name'] ?? '',
                    'type' => $a['type'] ?? '',
                    'is_postable' => (bool)($a['is_postable'] ?? true),
                ];
            })
            ->filter(fn($a) => !empty($a['id']) && (bool)$a['is_postable'] === true)
            ->values()
            ->all();

        return [$accounts, null];
    }

    public function index(Request $request)
    {
        [$accounts, $accountsError] = $this->fetchAccountOptions();

        // defaults periode: 1 Jan s/d 31 Des tahun ini
        $year = (string) $request->query('year', now()->format('Y'));
        $defaultFrom = $year . '-01-01';
        $defaultTo   = $year . '-12-31';

        $accountId = (string) $request->query('account_id', '');
        $fromDate  = (string) $request->query('from_date', $defaultFrom);
        $toDate    = (string) $request->query('to_date', $defaultTo);

        $ledger = null;
        $apiError = null;

        if ($accountId) {
            $res = FinanceApiHelper::get('/v1/ledgers', [
                'account_id' => $accountId,
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ]);

            if (!($res['success'] ?? false)) {
                $apiError = $res['message'] ?? 'Failed to load ledger';
            } else {
                $ledger = data_get($res, 'data.data', null);
            }
        }

        // account selected label untuk header
        $selectedAccount = collect($accounts)->firstWhere('id', $accountId);

        return view('finance.ledgers.index', [
            'accounts' => $accounts,
            'accountsError' => $accountsError,

            'year' => $year,
            'accountId' => $accountId,
            'fromDate' => $fromDate,
            'toDate' => $toDate,

            'ledger' => $ledger,
            'apiError' => $apiError,
            'selectedAccount' => $selectedAccount,
        ]);
    }
}

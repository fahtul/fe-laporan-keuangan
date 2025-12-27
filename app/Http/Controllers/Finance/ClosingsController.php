<?php

namespace App\Http\Controllers\Finance;

use App\Helpers\FinanceApiHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ClosingsController extends Controller
{
    private function fetchEquityAccountOptions(): array
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

        $accounts = collect($items)
            ->map(function ($a) {
                return [
                    'id' => data_get($a, 'id'),
                    'code' => (string) data_get($a, 'code', ''),
                    'name' => (string) data_get($a, 'name', ''),
                    'type' => (string) data_get($a, 'type', ''),
                    'is_postable' => (bool) data_get($a, 'is_postable', true),
                ];
            })
            ->filter(function ($a) {
                if (empty($a['id']) || $a['is_postable'] !== true) {
                    return false;
                }
                return $a['type'] === 'equity';
            })
            ->values()
            ->all();

        return [$accounts, null];
    }

    public function index(Request $request)
    {
        $year = (string) $request->query('year', now()->format('Y'));
        if (!preg_match('/^\d{4}$/', $year)) {
            $year = now()->format('Y');
        }

        $res = FinanceApiHelper::get('/v1/closings/year-end', [
            'year' => $year,
        ]);

        $apiError = null;
        $closing = null;
        $isClosed = false;
        $closingEntryId = null;
        $openingEntryId = null;

        if (!($res['success'] ?? false)) {
            $apiError = $res['message'] ?? 'Failed to load closing status';
        } else {
            $json = $res['data'] ?? null;
            $payload = data_get($json, 'data.data') ?? data_get($json, 'data') ?? null;

            $closing = $payload;
            $closingEntryId = data_get($payload, 'closing_entry_id')
                ?? data_get($payload, 'closing_entry.id')
                ?? data_get($payload, 'closingEntryId');
            $openingEntryId = data_get($payload, 'opening_entry_id')
                ?? data_get($payload, 'opening_entry.id')
                ?? data_get($payload, 'openingEntryId');

            $status = strtolower((string) (data_get($payload, 'status') ?? ''));
            $isClosed = $status === 'closed' || $status === 'success' || !empty($closingEntryId);
        }

        [$equityAccounts, $accountsError] = $this->fetchEquityAccountOptions();

        return view('finance.closings.year_end', [
            'year' => $year,
            'closing' => $closing,
            'isClosed' => $isClosed,
            'closingEntryId' => $closingEntryId,
            'openingEntryId' => $openingEntryId,
            'equityAccounts' => $equityAccounts,
            'accountsError' => $accountsError,
            'apiError' => $apiError,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'year' => ['required', 'regex:/^\d{4}$/'],
            'retained_earnings_account_id' => 'required|string',
            'date' => 'nullable|date',
            'memo' => 'nullable|string|max:2000',
            'generate_opening' => 'nullable|string',
        ]);

        $year = (string) $validated['year'];
        $date = (string) ($validated['date'] ?? '');
        if ($date === '') {
            $date = $year . '-12-31';
        }

        $generateOpening = $request->boolean('generate_opening');

        $payload = [
            'year' => $year,
            'date' => $date,
            'retained_earnings_account_id' => (string) $validated['retained_earnings_account_id'],
            'memo' => isset($validated['memo']) && trim((string) $validated['memo']) !== '' ? (string) $validated['memo'] : null,
            'generate_opening' => $generateOpening,
        ];

        $res = FinanceApiHelper::post('/v1/closings/year-end', $payload);

        if (!($res['success'] ?? false)) {
            return back()
                ->withErrors(['api' => $res['message'] ?? 'Gagal menjalankan closing'])
                ->withInput();
        }

        $json = $res['data'] ?? null;
        $result = data_get($json, 'data.data') ?? data_get($json, 'data') ?? [];

        $closingEntryId = data_get($result, 'closing_entry_id') ?? data_get($result, 'closingEntryId');
        $openingEntryId = data_get($result, 'opening_entry_id') ?? data_get($result, 'openingEntryId');
        $netProfit = data_get($result, 'net_profit') ?? data_get($result, 'summary.net_profit_after_tax');

        return redirect()
            ->route('finance.closings.year_end.index', ['year' => $year])
            ->with('success', 'Year-end closing berhasil dijalankan')
            ->with('closing_result', [
                'net_profit' => $netProfit,
                'closing_entry_id' => $closingEntryId,
                'opening_entry_id' => $openingEntryId,
            ]);
    }
}


<?php

namespace App\Reports\ExportTransformers;

use Illuminate\Support\Arr;

class BalanceSheetExportTransformer
{
    public function transform(mixed $payload, array $params = []): array
    {
        $asOf = (string) Arr::get((array) $payload, 'as_of', '');

        $sections = (array) Arr::get((array) $payload, 'sections', []);
        $totals = (array) Arr::get((array) $payload, 'totals', []);

        $assets = (array) Arr::get($sections, 'assets', []);
        $liabilities = (array) Arr::get($sections, 'liabilities', []);
        $equity = (array) Arr::get($sections, 'equity', []);
        $currentProfit = (array) Arr::get($sections, 'current_profit', []);

        $rows = [];
        $rows[] = ['type' => 'note', 'level' => 0, 'label' => $asOf !== '' ? "As of: {$asOf}" : ''];

        $this->pushSection($rows, 'ASET', (array) Arr::get($assets, 'items', []), (float) Arr::get($assets, 'total', Arr::get($totals, 'assets_total', 0)));
        $this->pushSection($rows, 'KEWAJIBAN', (array) Arr::get($liabilities, 'items', []), (float) Arr::get($liabilities, 'total', Arr::get($totals, 'liabilities_total', 0)));

        // Equity + current profit line
        $equityItems = (array) Arr::get($equity, 'items', []);
        $profitAmount = (float) Arr::get($currentProfit, 'amount', 0);
        if (!empty($currentProfit)) {
            $equityItems[] = [
                'code' => '-',
                'name' => 'LABA BERJALAN',
                'amount' => $profitAmount,
                'level' => 0,
            ];
        }
        $this->pushSection($rows, 'EKUITAS', $equityItems, (float) Arr::get($equity, 'total', Arr::get($totals, 'equity_total', 0)));

        $rhsTotal = (float) Arr::get($totals, 'liabilities_plus_equity', 0);
        if ($rhsTotal !== 0.0) {
            $rows[] = ['type' => 'grand_total', 'level' => 0, 'label' => 'TOTAL KEWAJIBAN + EKUITAS', 'amount' => $rhsTotal];
        }

        $diff = (float) Arr::get($totals, 'difference', 0);
        if (abs($diff) >= 0.00001) {
            $rows[] = ['type' => 'account', 'level' => 0, 'label' => 'SELISIH', 'amount' => $diff];
        }

        return [
            'title' => 'Neraca',
            'period' => [
                'as_of' => $asOf,
            ],
            'columns' => [
                ['key' => 'code', 'label' => 'Code', 'align' => 'left'],
                ['key' => 'label', 'label' => 'Nama', 'align' => 'left'],
                ['key' => 'amount', 'label' => 'Jumlah', 'align' => 'right'],
            ],
            'rows' => $rows,
            'meta' => [
                'generated_at' => now()->toDateTimeString(),
            ],
        ];
    }

    private function pushSection(array &$rows, string $title, array $items, float $total): void
    {
        $rows[] = ['type' => 'section', 'level' => 0, 'label' => $title];

        foreach ($items as $it) {
            $level = (int) Arr::get((array) $it, 'level', 0);
            $rows[] = [
                'type' => 'account',
                'level' => 1 + max(0, $level),
                'code' => (string) Arr::get((array) $it, 'code', ''),
                'label' => (string) Arr::get((array) $it, 'name', ''),
                'amount' => (float) Arr::get((array) $it, 'amount', 0),
            ];
        }

        $rows[] = ['type' => 'subtotal', 'level' => 0, 'label' => "TOTAL {$title}", 'amount' => $total];
        $rows[] = ['type' => 'spacer'];
    }
}


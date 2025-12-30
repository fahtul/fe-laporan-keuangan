<?php

namespace App\Reports\ExportTransformers;

use Illuminate\Support\Arr;

class CashFlowExportTransformer
{
    public function transform(mixed $payload, array $params = []): array
    {
        $period = (array) Arr::get((array) $payload, 'period', []);
        $activities = (array) Arr::get((array) $payload, 'activities', []);
        $totals = (array) Arr::get((array) $payload, 'totals', []);
        $cash = (array) Arr::get((array) $payload, 'cash', []);
        $reconciliation = (array) Arr::get((array) $payload, 'reconciliation', []);

        $rows = [];

        $activityKeys = [
            'operating' => 'OPERATING (CFO)',
            'investing' => 'INVESTING (CFI)',
            'financing' => 'FINANCING (CFF)',
        ];

        foreach ($activityKeys as $k => $label) {
            $block = (array) Arr::get($activities, $k, []);
            $items = (array) Arr::get($block, 'items', []);
            $total = (float) Arr::get($block, 'total', Arr::get($totals, $k, 0));

            $rows[] = ['type' => 'section', 'level' => 0, 'label' => $label];

            foreach ($items as $it) {
                $code = (string) Arr::get((array) $it, 'code', '');
                $name = (string) Arr::get((array) $it, 'name', '');
                $amount = (float) Arr::get((array) $it, 'amount', 0);

                $rows[] = [
                    'type' => 'account',
                    'level' => 1,
                    'label' => trim($code !== '' ? "{$code} - {$name}" : $name),
                    'amount' => $amount,
                ];
            }

            $rows[] = ['type' => 'subtotal', 'level' => 0, 'label' => "TOTAL {$label}", 'amount' => $total];
            $rows[] = ['type' => 'spacer'];
        }

        $begin = (float) Arr::get($cash, 'begin', Arr::get($cash, 'beginning', 0));
        $change = (float) Arr::get($cash, 'change', Arr::get($cash, 'net_change', 0));
        $end = (float) Arr::get($cash, 'end', Arr::get($cash, 'ending', 0));

        $netChange = $change;
        if ($netChange == 0.0) {
            $netChange = (float) Arr::get($totals, 'net_change', 0);
        }

        $endCalc = (float) Arr::get($reconciliation, 'end_calc', $begin + $netChange);
        if ($end == 0.0) {
            $end = $endCalc;
        }

        $rows[] = ['type' => 'grand_total', 'level' => 0, 'label' => 'NET CHANGE', 'amount' => $netChange];
        $rows[] = ['type' => 'account', 'level' => 0, 'label' => 'BEGINNING CASH', 'amount' => $begin];
        $rows[] = ['type' => 'grand_total', 'level' => 0, 'label' => 'ENDING CASH', 'amount' => $end];

        return [
            'title' => 'Arus Kas',
            'period' => [
                'from_date' => (string) Arr::get($period, 'from_date', ''),
                'to_date' => (string) Arr::get($period, 'to_date', ''),
            ],
            'columns' => [
                ['key' => 'label', 'label' => 'Nama', 'align' => 'left'],
                ['key' => 'amount', 'label' => 'Jumlah', 'align' => 'right'],
            ],
            'rows' => $rows,
            'meta' => [
                'generated_at' => now()->toDateTimeString(),
            ],
        ];
    }
}


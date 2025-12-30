<?php

namespace App\Reports\ExportTransformers;

use Illuminate\Support\Arr;

class TrialBalanceExportTransformer
{
    public function transform(mixed $payload, array $params = []): array
    {
        $period = (array) Arr::get((array) $payload, 'period', []);
        $items = (array) Arr::get((array) $payload, 'items', []);
        $totals = (array) Arr::get((array) $payload, 'totals', []);

        $rows = [];

        foreach ($items as $it) {
            $rows[] = [
                'type' => 'account',
                'level' => (int) Arr::get((array) $it, 'level', 0),
                'code' => (string) Arr::get((array) $it, 'code', ''),
                'label' => (string) Arr::get((array) $it, 'name', ''),
                'type_name' => (string) Arr::get((array) $it, 'type', ''),
                'opening_debit' => (float) Arr::get((array) $it, 'opening.debit', 0),
                'opening_credit' => (float) Arr::get((array) $it, 'opening.credit', 0),
                'mutation_debit' => (float) Arr::get((array) $it, 'mutation.debit', 0),
                'mutation_credit' => (float) Arr::get((array) $it, 'mutation.credit', 0),
                'closing_debit' => (float) Arr::get((array) $it, 'closing.debit', 0),
                'closing_credit' => (float) Arr::get((array) $it, 'closing.credit', 0),
            ];
        }

        $rows[] = [
            'type' => 'grand_total',
            'level' => 0,
            'code' => '',
            'label' => 'TOTAL',
            'type_name' => '',
            'opening_debit' => (float) Arr::get($totals, 'opening_debit', 0),
            'opening_credit' => (float) Arr::get($totals, 'opening_credit', 0),
            'mutation_debit' => (float) Arr::get($totals, 'mutation_debit', 0),
            'mutation_credit' => (float) Arr::get($totals, 'mutation_credit', 0),
            'closing_debit' => (float) Arr::get($totals, 'closing_debit', 0),
            'closing_credit' => (float) Arr::get($totals, 'closing_credit', 0),
        ];

        return [
            'title' => 'Neraca Saldo',
            'period' => [
                'from_date' => (string) Arr::get($period, 'from_date', ''),
                'to_date' => (string) Arr::get($period, 'to_date', ''),
            ],
            'columns' => [
                ['key' => 'code', 'label' => 'Code', 'align' => 'left'],
                ['key' => 'label', 'label' => 'Nama', 'align' => 'left'],
                ['key' => 'type_name', 'label' => 'Type', 'align' => 'left'],
                ['key' => 'opening_debit', 'label' => 'Awal (D)', 'align' => 'right'],
                ['key' => 'opening_credit', 'label' => 'Awal (K)', 'align' => 'right'],
                ['key' => 'mutation_debit', 'label' => 'Mutasi (D)', 'align' => 'right'],
                ['key' => 'mutation_credit', 'label' => 'Mutasi (K)', 'align' => 'right'],
                ['key' => 'closing_debit', 'label' => 'Akhir (D)', 'align' => 'right'],
                ['key' => 'closing_credit', 'label' => 'Akhir (K)', 'align' => 'right'],
            ],
            'rows' => $rows,
            'meta' => [
                'generated_at' => now()->toDateTimeString(),
            ],
        ];
    }
}


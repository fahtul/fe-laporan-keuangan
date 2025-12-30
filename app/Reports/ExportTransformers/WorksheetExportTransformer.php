<?php

namespace App\Reports\ExportTransformers;

use Illuminate\Support\Arr;

class WorksheetExportTransformer
{
    public function transform(mixed $payload, array $params = []): array
    {
        $period = (array) Arr::get((array) $payload, 'period', [
            'from_date' => (string) ($params['from_date'] ?? ''),
            'to_date' => (string) ($params['to_date'] ?? ''),
        ]);

        $items = (array) Arr::get((array) $payload, 'items', []);
        $virtualRows = (array) Arr::get((array) $payload, 'virtual_rows', []);
        $totals = (array) Arr::get((array) $payload, 'totals', []);

        $rows = [];

        foreach ($items as $it) {
            $rows[] = [
                'type' => 'account',
                'level' => (int) Arr::get((array) $it, 'level', 0),
                'code' => (string) Arr::get((array) $it, 'code', ''),
                'label' => (string) Arr::get((array) $it, 'name', ''),
                'opening_debit' => (float) Arr::get((array) $it, 'opening.debit', Arr::get((array) $it, 'opening_debit', 0)),
                'opening_credit' => (float) Arr::get((array) $it, 'opening.credit', Arr::get((array) $it, 'opening_credit', 0)),
                'mutation_debit' => (float) Arr::get((array) $it, 'mutation.debit', Arr::get((array) $it, 'mutation_debit', 0)),
                'mutation_credit' => (float) Arr::get((array) $it, 'mutation.credit', Arr::get((array) $it, 'mutation_credit', 0)),
                'closing_debit' => (float) Arr::get((array) $it, 'closing.debit', Arr::get((array) $it, 'closing_debit', 0)),
                'closing_credit' => (float) Arr::get((array) $it, 'closing.credit', Arr::get((array) $it, 'closing_credit', 0)),
                'pnl_debit' => (float) Arr::get((array) $it, 'pnl.debit', Arr::get((array) $it, 'pnl_debit', 0)),
                'pnl_credit' => (float) Arr::get((array) $it, 'pnl.credit', Arr::get((array) $it, 'pnl_credit', 0)),
                'final_debit' => (float) Arr::get((array) $it, 'final.debit', Arr::get((array) $it, 'final_debit', 0)),
                'final_credit' => (float) Arr::get((array) $it, 'final.credit', Arr::get((array) $it, 'final_credit', 0)),
            ];
        }

        foreach ($virtualRows as $vr) {
            $rows[] = [
                'type' => 'section',
                'level' => 0,
                'code' => (string) Arr::get((array) $vr, 'code', ''),
                'label' => (string) Arr::get((array) $vr, 'name', 'Virtual'),
                'opening_debit' => '',
                'opening_credit' => '',
                'mutation_debit' => '',
                'mutation_credit' => '',
                'closing_debit' => '',
                'closing_credit' => '',
                'pnl_debit' => '',
                'pnl_credit' => '',
                'final_debit' => (float) Arr::get((array) $vr, 'final.debit', Arr::get((array) $vr, 'final_debit', 0)),
                'final_credit' => (float) Arr::get((array) $vr, 'final.credit', Arr::get((array) $vr, 'final_credit', 0)),
            ];
        }

        if (!empty($totals)) {
            $rows[] = [
                'type' => 'grand_total',
                'level' => 0,
                'code' => '',
                'label' => 'TOTAL',
                'opening_debit' => (float) Arr::get($totals, 'opening.debit', Arr::get($totals, 'opening_debit', 0)),
                'opening_credit' => (float) Arr::get($totals, 'opening.credit', Arr::get($totals, 'opening_credit', 0)),
                'mutation_debit' => (float) Arr::get($totals, 'mutation.debit', Arr::get($totals, 'mutation_debit', 0)),
                'mutation_credit' => (float) Arr::get($totals, 'mutation.credit', Arr::get($totals, 'mutation_credit', 0)),
                'closing_debit' => (float) Arr::get($totals, 'closing.debit', Arr::get($totals, 'closing_debit', 0)),
                'closing_credit' => (float) Arr::get($totals, 'closing.credit', Arr::get($totals, 'closing_credit', 0)),
                'pnl_debit' => (float) Arr::get($totals, 'pnl.debit', Arr::get($totals, 'pnl_debit', 0)),
                'pnl_credit' => (float) Arr::get($totals, 'pnl.credit', Arr::get($totals, 'pnl_credit', 0)),
                'final_debit' => (float) Arr::get($totals, 'final.debit', Arr::get($totals, 'final_debit', 0)),
                'final_credit' => (float) Arr::get($totals, 'final.credit', Arr::get($totals, 'final_credit', 0)),
            ];
        }

        return [
            'title' => 'Neraca Lajur',
            'period' => [
                'from_date' => (string) Arr::get($period, 'from_date', ''),
                'to_date' => (string) Arr::get($period, 'to_date', ''),
            ],
            'columns' => [
                ['key' => 'code', 'label' => 'Code', 'align' => 'left'],
                ['key' => 'label', 'label' => 'Nama', 'align' => 'left'],
                ['key' => 'opening_debit', 'label' => 'Awal (D)', 'align' => 'right'],
                ['key' => 'opening_credit', 'label' => 'Awal (K)', 'align' => 'right'],
                ['key' => 'mutation_debit', 'label' => 'Mutasi (D)', 'align' => 'right'],
                ['key' => 'mutation_credit', 'label' => 'Mutasi (K)', 'align' => 'right'],
                ['key' => 'closing_debit', 'label' => 'Saldo (D)', 'align' => 'right'],
                ['key' => 'closing_credit', 'label' => 'Saldo (K)', 'align' => 'right'],
                ['key' => 'pnl_debit', 'label' => 'L/R (D)', 'align' => 'right'],
                ['key' => 'pnl_credit', 'label' => 'L/R (K)', 'align' => 'right'],
                ['key' => 'final_debit', 'label' => 'Akhir (D)', 'align' => 'right'],
                ['key' => 'final_credit', 'label' => 'Akhir (K)', 'align' => 'right'],
            ],
            'rows' => $rows,
            'meta' => [
                'generated_at' => now()->toDateTimeString(),
            ],
        ];
    }
}


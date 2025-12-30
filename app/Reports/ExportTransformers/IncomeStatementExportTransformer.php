<?php

namespace App\Reports\ExportTransformers;

use Illuminate\Support\Arr;

class IncomeStatementExportTransformer
{
    public function transform(mixed $payload, array $params = []): array
    {
        $period = (array) Arr::get((array) $payload, 'period', []);
        $sections = (array) Arr::get((array) $payload, 'sections', []);
        $summary = (array) Arr::get((array) $payload, 'summary', []);

        $rows = [];

        foreach ($sections as $section) {
            $title = (string) Arr::get((array) $section, 'title', '');
            $items = (array) Arr::get((array) $section, 'items', []);
            $total = (float) Arr::get((array) $section, 'total', 0);

            $rows[] = ['type' => 'section', 'level' => 0, 'label' => $title];

            foreach ($items as $it) {
                $rows[] = [
                    'type' => 'account',
                    'level' => 1,
                    'code' => (string) Arr::get((array) $it, 'code', ''),
                    'label' => (string) Arr::get((array) $it, 'name', ''),
                    'amount' => (float) Arr::get((array) $it, 'amount', 0),
                ];
            }

            $rows[] = ['type' => 'subtotal', 'level' => 0, 'label' => "JUMLAH {$title}", 'amount' => $total];
            $rows[] = ['type' => 'spacer'];
        }

        // Summary block (flat)
        $rows[] = ['type' => 'section', 'level' => 0, 'label' => 'RINGKASAN'];
        $rows[] = ['type' => 'account', 'level' => 1, 'label' => 'JUMLAH PENDAPATAN', 'amount' => (float) Arr::get($summary, 'total_revenue', 0)];
        $rows[] = ['type' => 'account', 'level' => 1, 'label' => 'JUMLAH HPP', 'amount' => (float) Arr::get($summary, 'total_cogs', 0)];
        $rows[] = ['type' => 'account', 'level' => 1, 'label' => 'LABA KOTOR', 'amount' => (float) Arr::get($summary, 'gross_profit', 0)];
        $rows[] = ['type' => 'account', 'level' => 1, 'label' => 'JUMLAH BIAYA OPERASIONAL', 'amount' => (float) Arr::get($summary, 'total_operating_expense', 0)];
        $rows[] = ['type' => 'account', 'level' => 1, 'label' => 'LABA BERSIH (OPERASIONAL)', 'amount' => (float) Arr::get($summary, 'operating_profit', 0)];
        $taxRate = Arr::get($summary, 'tax_rate', Arr::get($params, 'tax_rate'));
        $taxLabel = $taxRate !== null && $taxRate !== '' ? 'PAJAK (' . ((float) $taxRate * 100) . '%)' : 'PAJAK';
        $rows[] = ['type' => 'account', 'level' => 1, 'label' => $taxLabel, 'amount' => (float) Arr::get($summary, 'tax_amount', 0)];
        $rows[] = ['type' => 'grand_total', 'level' => 0, 'label' => 'LABA BERSIH SETELAH PAJAK', 'amount' => (float) Arr::get($summary, 'net_profit_after_tax', 0)];

        return [
            'title' => 'Laba Rugi',
            'period' => [
                'from_date' => (string) Arr::get($period, 'from_date', ''),
                'to_date' => (string) Arr::get($period, 'to_date', ''),
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
}


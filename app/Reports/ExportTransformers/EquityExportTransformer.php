<?php

namespace App\Reports\ExportTransformers;

use Illuminate\Support\Arr;

class EquityExportTransformer
{
    public function transform(mixed $payload, array $params = []): array
    {
        $period = (array) Arr::get((array) $payload, 'period', [
            'from_date' => (string) ($params['from_date'] ?? ''),
            'to_date' => (string) ($params['to_date'] ?? ''),
        ]);

        $opening = (array) Arr::get((array) $payload, 'opening', []);
        $closing = (array) Arr::get((array) $payload, 'closing', []);
        $movements = (array) Arr::get((array) $payload, 'movements', []);

        $rows = [];

        $rows[] = ['type' => 'section', 'level' => 0, 'label' => 'EQUITY AWAL'];
        foreach ((array) Arr::get($opening, 'items', []) as $it) {
            $rows[] = [
                'type' => 'account',
                'level' => 1,
                'code' => (string) Arr::get((array) $it, 'code', ''),
                'label' => (string) Arr::get((array) $it, 'name', ''),
                'amount' => (float) Arr::get((array) $it, 'amount', 0),
            ];
        }
        $openingTotal = (float) (Arr::get($opening, 'total.amount') ?? Arr::get($opening, 'total') ?? 0);
        $rows[] = ['type' => 'subtotal', 'level' => 0, 'label' => 'TOTAL EQUITY AWAL', 'amount' => $openingTotal];
        $rows[] = ['type' => 'spacer'];

        $categories = (array) Arr::get($movements, 'categories', []);
        if (!empty($categories)) {
            $rows[] = ['type' => 'section', 'level' => 0, 'label' => 'MOVEMENTS'];

            foreach ($categories as $cat) {
                $items = (array) Arr::get((array) $cat, 'items', []);
                if (empty($items)) {
                    continue;
                }

                $key = strtolower((string) Arr::get((array) $cat, 'key', Arr::get((array) $cat, 'type', '')));
                $label = (string) Arr::get((array) $cat, 'label', Arr::get((array) $cat, 'name', strtoupper($key)));
                if ($key === 'profit') {
                    $label = 'Laba periode berjalan';
                }

                $rows[] = ['type' => 'section', 'level' => 1, 'label' => $label];

                foreach ($items as $it) {
                    $rows[] = [
                        'type' => 'account',
                        'level' => 2,
                        'code' => (string) Arr::get((array) $it, 'code', ''),
                        'label' => (string) Arr::get((array) $it, 'name', ''),
                        'amount' => (float) Arr::get((array) $it, 'amount', 0),
                    ];
                }

                $catTotal = (float) (Arr::get((array) $cat, 'total.amount') ?? Arr::get((array) $cat, 'amount') ?? 0);
                $rows[] = ['type' => 'subtotal', 'level' => 1, 'label' => "TOTAL {$label}", 'amount' => $catTotal];
                $rows[] = ['type' => 'spacer'];
            }
        }

        $rows[] = ['type' => 'section', 'level' => 0, 'label' => 'EQUITY AKHIR'];
        foreach ((array) Arr::get($closing, 'items', []) as $it) {
            $rows[] = [
                'type' => 'account',
                'level' => 1,
                'code' => (string) Arr::get((array) $it, 'code', ''),
                'label' => (string) Arr::get((array) $it, 'name', ''),
                'amount' => (float) Arr::get((array) $it, 'amount', 0),
            ];
        }
        $closingTotal = (float) (Arr::get($closing, 'total.amount') ?? Arr::get($closing, 'total') ?? 0);
        $rows[] = ['type' => 'grand_total', 'level' => 0, 'label' => 'TOTAL EQUITY AKHIR', 'amount' => $closingTotal];

        return [
            'title' => 'LP Equitas',
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


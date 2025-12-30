<?php

namespace App\Reports\ExportTransformers;

use Illuminate\Support\Arr;

class SubsidiaryLedgerExportTransformer
{
    public function transform(mixed $payload, array $params = []): array
    {
        $period = (array) Arr::get((array) $payload, 'period', [
            'from_date' => (string) ($params['from_date'] ?? ''),
            'to_date' => (string) ($params['to_date'] ?? ''),
        ]);

        $account = (array) Arr::get((array) $payload, 'account', []);
        $items = (array) Arr::get((array) $payload, 'items', []);
        $totals = (array) Arr::get((array) $payload, 'totals', []);

        $rows = [];

        $accCode = (string) Arr::get($account, 'code', '');
        $accName = (string) Arr::get($account, 'name', '');
        if ($accCode !== '' || $accName !== '') {
            $rows[] = ['type' => 'note', 'level' => 0, 'label' => trim("Akun: {$accCode} - {$accName}", ' -')];
            $rows[] = ['type' => 'spacer'];
        }

        foreach ($items as $it) {
            $bpCode = (string) (Arr::get((array) $it, 'bp_code') ?? Arr::get((array) $it, 'bp.code') ?? Arr::get((array) $it, 'code') ?? '');
            $bpName = (string) (Arr::get((array) $it, 'bp_name') ?? Arr::get((array) $it, 'bp.name') ?? Arr::get((array) $it, 'name') ?? '');

            $opening = (float) (Arr::get((array) $it, 'opening_balance') ?? Arr::get((array) $it, 'opening.amount') ?? Arr::get((array) $it, 'opening_amount') ?? 0);
            $mutD = (float) (Arr::get((array) $it, 'mutation.debit') ?? Arr::get((array) $it, 'mutation_debit') ?? Arr::get((array) $it, 'period_debit') ?? 0);
            $mutK = (float) (Arr::get((array) $it, 'mutation.credit') ?? Arr::get((array) $it, 'mutation_credit') ?? Arr::get((array) $it, 'period_credit') ?? 0);
            $closing = (float) (Arr::get((array) $it, 'closing_balance') ?? Arr::get((array) $it, 'closing.amount') ?? Arr::get((array) $it, 'closing_amount') ?? 0);

            $rows[] = [
                'type' => 'account',
                'level' => 0,
                'code' => $bpCode,
                'label' => $bpName,
                'opening' => $opening,
                'mutation_debit' => $mutD,
                'mutation_credit' => $mutK,
                'closing' => $closing,
            ];
        }

        if (!empty($totals)) {
            $rows[] = ['type' => 'grand_total', 'level' => 0, 'code' => '', 'label' => 'TOTAL', 'opening' => (float) Arr::get($totals, 'opening_balance', 0), 'mutation_debit' => (float) Arr::get($totals, 'mutation_debit', 0), 'mutation_credit' => (float) Arr::get($totals, 'mutation_credit', 0), 'closing' => (float) Arr::get($totals, 'closing_balance', 0)];
        }

        return [
            'title' => 'Buku Pembantu',
            'period' => [
                'from_date' => (string) Arr::get($period, 'from_date', ''),
                'to_date' => (string) Arr::get($period, 'to_date', ''),
            ],
            'columns' => [
                ['key' => 'code', 'label' => 'Kode BP', 'align' => 'left'],
                ['key' => 'label', 'label' => 'Nama BP', 'align' => 'left'],
                ['key' => 'opening', 'label' => 'Saldo Awal', 'align' => 'right'],
                ['key' => 'mutation_debit', 'label' => 'Mutasi (D)', 'align' => 'right'],
                ['key' => 'mutation_credit', 'label' => 'Mutasi (K)', 'align' => 'right'],
                ['key' => 'closing', 'label' => 'Saldo Akhir', 'align' => 'right'],
            ],
            'rows' => $rows,
            'meta' => [
                'generated_at' => now()->toDateTimeString(),
            ],
        ];
    }
}


<?php

namespace App\Reports\ExportTransformers;

use Illuminate\Support\Arr;

class LedgerExportTransformer
{
    public function transform(mixed $payload, array $params = []): array
    {
        $account = (array) Arr::get((array) $payload, 'account', []);
        $rows = (array) Arr::get((array) $payload, 'rows', []);

        $period = (array) Arr::get((array) $payload, 'period', []);
        if (empty($period)) {
            $period = [
                'from_date' => (string) ($params['from_date'] ?? ''),
                'to_date' => (string) ($params['to_date'] ?? ''),
            ];
        }

        $out = [];

        $accCode = (string) Arr::get($account, 'code', '');
        $accName = (string) Arr::get($account, 'name', '');
        if ($accCode !== '' || $accName !== '') {
            $out[] = ['type' => 'note', 'level' => 0, 'label' => trim("Akun: {$accCode} - {$accName}", ' -')];
            $out[] = ['type' => 'spacer'];
        }

        foreach ($rows as $r) {
            $kind = strtolower((string) Arr::get((array) $r, 'kind', ''));
            $date = (string) Arr::get((array) $r, 'date', '');
            $entryId = (string) Arr::get((array) $r, 'entry_id', '');
            $ref = (string) Arr::get((array) $r, 'ref', $entryId !== '' ? $entryId : '');
            $desc = (string) Arr::get((array) $r, 'description', '');

            $debit = (float) Arr::get((array) $r, 'debit', 0);
            $credit = (float) Arr::get((array) $r, 'credit', 0);
            $balance = (float) Arr::get((array) $r, 'running_balance', 0);
            $pos = (string) Arr::get((array) $r, 'running_pos', '');

            $label = $kind === 'opening' ? 'Saldo awal' : $desc;
            if ($pos !== '') {
                $label = $label . ' (' . strtoupper($pos) . ')';
            }

            $out[] = [
                'type' => $kind === 'opening' ? 'section' : 'account',
                'level' => 0,
                'date' => $date,
                'code' => $ref,
                'label' => $label,
                'debit' => $kind === 'opening' ? '' : $debit,
                'credit' => $kind === 'opening' ? '' : $credit,
                'balance' => $balance,
            ];
        }

        $totals = (array) Arr::get((array) $payload, 'totals', []);
        $periodDebit = (float) Arr::get($totals, 'period_debit', 0);
        $periodCredit = (float) Arr::get($totals, 'period_credit', 0);
        $closingBal = (float) Arr::get($totals, 'closing_balance', 0);

        if (!empty($totals)) {
            $out[] = ['type' => 'spacer'];
            $out[] = [
                'type' => 'grand_total',
                'level' => 0,
                'date' => '',
                'code' => '',
                'label' => 'TOTAL PERIODE',
                'debit' => $periodDebit,
                'credit' => $periodCredit,
                'balance' => $closingBal,
            ];
        }

        return [
            'title' => 'Buku Besar',
            'period' => [
                'from_date' => (string) Arr::get($period, 'from_date', ''),
                'to_date' => (string) Arr::get($period, 'to_date', ''),
            ],
            'columns' => [
                ['key' => 'date', 'label' => 'Tanggal', 'align' => 'left'],
                ['key' => 'code', 'label' => 'Bukti', 'align' => 'left'],
                ['key' => 'label', 'label' => 'Keterangan', 'align' => 'left'],
                ['key' => 'debit', 'label' => 'Debit', 'align' => 'right'],
                ['key' => 'credit', 'label' => 'Kredit', 'align' => 'right'],
                ['key' => 'balance', 'label' => 'Saldo', 'align' => 'right'],
            ],
            'rows' => $out,
            'meta' => [
                'generated_at' => now()->toDateTimeString(),
            ],
        ];
    }
}


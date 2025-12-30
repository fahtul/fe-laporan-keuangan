@extends('finance.report-layout')

@section('title', 'Buku Pembantu – Detail')
@section('subtitle', 'Running Balance per BP')

@section('header_actions')
    <a class="px-4 py-2 rounded border" href="{{ $backUrl }}">Kembali</a>
@endsection

@section('header_meta')
    @php
        $bpCode = (string) data_get($bp, 'code', '');
        $bpName = (string) data_get($bp, 'name', '');
        $accCode = (string) data_get($account, 'code', '');
        $accName = (string) data_get($account, 'name', '');

        $openAmt =
            (float) (data_get($opening, 'amount') ??
                (data_get($opening, 'balance') ?? (data_get($opening, 'opening_balance') ?? 0)));
        $openSide =
            (string) (data_get($opening, 'side') ??
                (data_get($opening, 'pos') ?? (data_get($opening, 'opening_pos') ?? '')));

        $closeAmt =
            (float) (data_get($closing, 'amount') ??
                (data_get($closing, 'balance') ?? (data_get($closing, 'closing_balance') ?? 0)));
        $closeSide =
            (string) (data_get($closing, 'side') ??
                (data_get($closing, 'pos') ?? (data_get($closing, 'closing_pos') ?? '')));
    @endphp

    <span class="report-chip">BP: <span class="font-semibold">{{ $bpCode }} - {{ $bpName }}</span></span>
    <span class="report-chip">Akun: <span class="font-semibold">{{ $accCode }} - {{ $accName }}</span></span>
    <span class="report-chip">Periode: <span class="font-semibold">{{ data_get($period, 'from_date', $fromDate) }} -
            {{ data_get($period, 'to_date', $toDate) }}</span></span>
    <span class="report-chip">Saldo Awal: <span class="font-semibold">{{ number_format($openAmt, 2, ',', '.') }}
            {{ strtoupper($openSide) }}</span></span>
    <span class="report-chip">Saldo Akhir: <span class="font-semibold">{{ number_format($closeAmt, 2, ',', '.') }}
            {{ strtoupper($closeSide) }}</span></span>
@endsection

@section('tools')
    <style>
        .table-wrap {
            max-height: 70vh;
            overflow: auto;
        }

        .sl-table thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #f9fafb;
        }

        .sl-table tbody tr:hover {
            background: #fafafa;
        }
    </style>

    <div class="bg-white border rounded p-4 flex flex-wrap items-end justify-between gap-3">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 flex-1">
            <div>
                <label class="block text-sm mb-1">Akun</label>
                <input class="border rounded px-3 py-2 w-full bg-gray-50" value="{{ $accountId }}" readonly>
            </div>
            <div>
                <label class="block text-sm mb-1">Dari</label>
                <input class="border rounded px-3 py-2 w-full bg-gray-50" value="{{ $fromDate }}" readonly>
            </div>
            <div>
                <label class="block text-sm mb-1">Sampai</label>
                <input class="border rounded px-3 py-2 w-full bg-gray-50" value="{{ $toDate }}" readonly>
            </div>
        </div>
        <a class="px-4 py-2 rounded border bg-white text-gray-900 hover:bg-gray-50" href="{{ $backUrl }}">Ubah
            Filter</a>
    </div>
@endsection

@section('content')
    @if (!empty($apiError))
        <div class="p-3 rounded bg-yellow-100 text-yellow-800">
            {{ $apiError }}
        </div>
    @endif

    @php
        $periodDebit = (float) (data_get($totals, 'period_debit') ?? (data_get($totals, 'mutation_debit') ?? 0));
        $periodCredit = (float) (data_get($totals, 'period_credit') ?? (data_get($totals, 'mutation_credit') ?? 0));

        $rowsArr = is_array($rows) ? $rows : [];
        $firstKind = count($rowsArr) > 0 ? (string) data_get($rowsArr[0], 'kind', '') : '';
        $hasOpeningRow = strtolower($firstKind) === 'opening';
    @endphp

    <div class="bg-white border rounded">
        <div class="table-wrap">
            <table class="sl-table min-w-full text-sm">
                <thead>
                    <tr class="text-left border-b">
                        <th class="p-3 w-14">No</th>
                        <th class="p-3 w-28">Tanggal</th>
                        <th class="p-3 w-40">Bukti</th>
                        <th class="p-3">Keterangan</th>
                        <th class="p-3 text-right w-32">Debit</th>
                        <th class="p-3 text-right w-32">Kredit</th>
                        <th class="p-3 text-right w-40">Saldo</th>
                    </tr>
                </thead>

                <tbody class="divide-y">
                    @if (!$hasOpeningRow)
                        <tr class="bg-green-50">
                            <td class="p-3 text-gray-600">-</td>
                            <td class="p-3 text-gray-600">-</td>
                            <td class="p-3 text-gray-600">-</td>
                            <td class="p-3 font-semibold text-green-700">Saldo awal</td>
                            <td class="p-3 text-right">0,00</td>
                            <td class="p-3 text-right">0,00</td>
                            <td class="p-3 text-right font-semibold">
                                {{ number_format($openAmt, 2, ',', '.') }}
                                <span class="text-xs text-gray-600">{{ strtoupper($openSide) }}</span>
                            </td>
                        </tr>
                    @endif

                    @forelse ($rows as $idx => $r)
                        @php
                            $kind = strtolower((string) data_get($r, 'kind', ''));
                            $date = (string) (data_get($r, 'date') ?? (data_get($r, 'txn_date') ?? ''));
                            $entryId = (string) (data_get($r, 'entry_id') ?? (data_get($r, 'journal_entry_id') ?? ''));
                            $desc =
                                (string) (data_get($r, 'description') ??
                                    (data_get($r, 'memo') ?? (data_get($r, 'ref') ?? '')));

                            $debit = (float) (data_get($r, 'debit') ?? 0);
                            $credit = (float) (data_get($r, 'credit') ?? 0);

                            $runningSigned = data_get($r, 'running_signed', null);

                            // AMBIL DARI FIELD YANG BENAR
                            $bal =
                                (float) (data_get($r, 'running_amount') ??
                                    (data_get($r, 'running_balance') ?? // fallback legacy
                                        ((is_numeric($runningSigned) ? abs((float) $runningSigned) : null) ??
                                            (data_get($r, 'balance') ?? 0))));

                            $balSide =
                                (string) (data_get($r, 'running_side') ??
                                    (data_get($r, 'balance_side') ?? (data_get($r, 'pos') ?? '')));

                            // kalau balSide kosong tapi running_signed ada, tentukan dari tanda
                            if ($balSide === '' && is_numeric($runningSigned)) {
                                $balSide = ((float) $runningSigned) >= 0 ? 'debit' : 'credit';
                            }
                        @endphp

                        <tr class="{{ $kind === 'opening' ? 'bg-green-50' : '' }}">
                            <td class="p-3 text-gray-600">{{ (int) $idx + 1 }}</td>
                            @php
                                $dateDisp = $date;
                                if (is_string($dateDisp) && strlen($dateDisp) >= 10) {
                                    // ISO "2024-12-31T16:00:00.000Z" -> "2024-12-31"
                                    $dateDisp = substr($dateDisp, 0, 10);
                                }
                            @endphp

                            <td class="p-3 whitespace-nowrap">{{ $dateDisp }}</td>
                            <td class="p-3 whitespace-nowrap">
                                @if ($entryId !== '')
                                    <a class="underline hover:text-indigo-700"
                                        href="{{ route('finance.journal_entries.edit', $entryId) }}">{{ $entryId }}</a>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="p-3">
                                @if ($kind === 'opening')
                                    <span class="font-semibold text-green-700">Saldo awal</span>
                                @else
                                    {{ $desc }}
                                @endif
                            </td>
                            <td class="p-3 text-right">{{ number_format($debit, 2, ',', '.') }}</td>
                            <td class="p-3 text-right">{{ number_format($credit, 2, ',', '.') }}</td>
                            <td class="p-3 text-right font-semibold">
                                {{ number_format($bal, 2, ',', '.') }}
                                <span class="text-xs text-gray-600">{{ strtoupper($balSide) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-4 text-gray-500">No data</td>
                        </tr>
                    @endforelse
                </tbody>

                <tfoot class="bg-gray-50 border-t">
                    <tr>
                        <td colspan="4" class="p-3 font-semibold text-right">TOTAL PERIODE</td>
                        <td class="p-3 text-right font-semibold">{{ number_format($periodDebit, 2, ',', '.') }}</td>
                        <td class="p-3 text-right font-semibold">{{ number_format($periodCredit, 2, ',', '.') }}</td>
                        <td class="p-3 text-right font-semibold">
                            {{ number_format($closeAmt, 2, ',', '.') }}
                            <span class="text-xs text-gray-600">{{ strtoupper($closeSide) }}</span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection

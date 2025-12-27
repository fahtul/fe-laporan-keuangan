@extends('finance.report-layout')

@section('title', 'Buku Pembantu')
@section('subtitle', 'AR/AP per Business Partner')

@section('header_actions')
    <a class="px-4 py-2 rounded border" href="{{ route('finance.subledgers.index', ['year' => $year]) }}">Reset</a>
@endsection

@section('header_meta')
    <span class="report-chip">Periode: <span class="font-semibold">{{ data_get($period, 'from_date', $fromDate) }} - {{ data_get($period, 'to_date', $toDate) }}</span></span>
    @if (!empty($selectedAccount))
        <span class="report-chip">Akun: <span class="font-semibold">{{ data_get($selectedAccount, 'code') }} - {{ data_get($selectedAccount, 'name') }}</span></span>
    @endif
    <span class="report-chip">Include zero: <span class="font-semibold">{{ $includeZero ? 'Ya' : 'Tidak' }}</span></span>
    @if ((int) data_get($meta, 'total', 0) > 0)
        <span class="report-chip">Total BP: <span class="font-semibold">{{ (int) data_get($meta, 'total', 0) }}</span></span>
    @endif
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

        .chip {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .125rem .5rem;
            border-radius: 999px;
            font-size: 11px;
            border: 1px solid rgba(0, 0, 0, .08);
            background: #fff;
            color: #374151;
        }
    </style>

    <form method="GET" action="{{ route('finance.subledgers.index') }}" class="bg-white border rounded p-4"
        id="subledgersFilterForm">
        <input type="hidden" name="year" value="{{ $year }}">

        @if (!empty($accountsError))
            <div class="p-3 rounded bg-yellow-100 text-yellow-800 mb-3">
                {{ $accountsError }}
            </div>
        @endif

        <div class="grid md:grid-cols-6 gap-3">
            <div class="md:col-span-2">
                <label class="block text-sm mb-1">Akun AR/AP</label>

                <input id="accountSearch" type="text" class="border rounded px-3 py-2 w-full mb-2"
                    placeholder="Cari akun (code / name)..." autocomplete="off">

                <select id="accountSelect" name="account_id" class="border rounded px-3 py-2 w-full">
                    <option value="">-- pilih akun --</option>
                    @foreach ($accounts as $a)
                        <option value="{{ $a['id'] }}"
                            data-label="{{ strtolower(($a['code'] ?? '') . ' ' . ($a['name'] ?? '')) }}"
                            {{ $accountId === $a['id'] ? 'selected' : '' }}>
                            {{ $a['code'] }} - {{ $a['name'] }}
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">Pilih akun yang membutuhkan Business Partner (AR/AP).</p>
            </div>

            <div>
                <label class="block text-sm mb-1">Dari</label>
                <input type="date" name="from_date" value="{{ $fromDate }}" class="border rounded px-3 py-2 w-full"
                    required>
            </div>

            <div>
                <label class="block text-sm mb-1">Sampai</label>
                <input type="date" name="to_date" value="{{ $toDate }}" class="border rounded px-3 py-2 w-full"
                    required>
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm mb-1">Cari BP</label>
                <input type="text" name="q" value="{{ $q }}" class="border rounded px-3 py-2 w-full"
                    placeholder="Kode/Nama BP..." autocomplete="off">
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-3">
            <input type="hidden" name="include_zero" value="0">
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="include_zero" value="1" class="rounded border-gray-300"
                    {{ $includeZero ? 'checked' : '' }}>
                <span class="text-sm">Include zero</span>
            </label>

            <div class="flex items-center gap-2">
                <label class="text-sm">Limit</label>
                <select name="limit" class="border rounded px-3 py-2">
                    @foreach ([20, 50, 100] as $l)
                        <option value="{{ $l }}" {{ (int) $limit === $l ? 'selected' : '' }}>{{ $l }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <button type="submit" class="px-4 py-2 rounded bg-indigo-600 text-white">Tampilkan</button>
                <button type="submit" name="preset" value="year"
                    class="px-4 py-2 rounded border bg-white text-gray-900 hover:bg-gray-50">Tahun ini</button>
                <button type="submit" name="preset" value="month"
                    class="px-4 py-2 rounded border bg-white text-gray-900 hover:bg-gray-50">Bulan ini</button>
                <a class="px-4 py-2 rounded border bg-white text-gray-900 hover:bg-gray-50"
                    href="{{ route('finance.subledgers.index', ['year' => $year]) }}">Reset</a>
            </div>
        </div>
    </form>
@endsection

@section('content')
    @if (!empty($apiError))
        <div class="p-3 rounded bg-yellow-100 text-yellow-800">
            {{ $apiError }}
        </div>
    @endif

    @if (empty($accountId))
        <div class="bg-white border rounded p-4">
            <div class="font-semibold text-gray-900">Pilih akun terlebih dahulu</div>
            <div class="text-sm text-gray-600 mt-1">Silakan pilih akun AR/AP, lalu klik <span class="font-semibold">Tampilkan</span>.</div>
        </div>
    @else
        @php
            $sumOpening = (float) (data_get($totals, 'opening_balance') ?? data_get($totals, 'opening_amount') ?? 0);
            $sumOpeningSide = (string) (data_get($totals, 'opening_pos') ?? data_get($totals, 'opening_side') ?? '');
            $sumMutDebit = (float) (data_get($totals, 'mutation_debit') ?? data_get($totals, 'period_debit') ?? 0);
            $sumMutCredit = (float) (data_get($totals, 'mutation_credit') ?? data_get($totals, 'period_credit') ?? 0);
            $sumClosing = (float) (data_get($totals, 'closing_balance') ?? data_get($totals, 'closing_amount') ?? 0);
            $sumClosingSide = (string) (data_get($totals, 'closing_pos') ?? data_get($totals, 'closing_side') ?? '');
        @endphp

        <div class="grid md:grid-cols-3 gap-3">
            <div class="bg-white border rounded p-4">
                <div class="text-xs text-gray-500">Total Saldo Awal</div>
                <div class="text-lg font-semibold">{{ number_format($sumOpening, 2, ',', '.') }}
                    @if ($sumOpeningSide !== '')
                        <span class="chip">{{ strtoupper($sumOpeningSide) }}</span>
                    @endif
                </div>
            </div>
            <div class="bg-white border rounded p-4">
                <div class="text-xs text-gray-500">Total Mutasi</div>
                <div class="text-sm text-gray-700 mt-1 flex flex-wrap gap-2">
                    <span>Debet: <span class="font-semibold">{{ number_format($sumMutDebit, 2, ',', '.') }}</span></span>
                    <span>Kredit: <span class="font-semibold">{{ number_format($sumMutCredit, 2, ',', '.') }}</span></span>
                </div>
            </div>
            <div class="bg-white border rounded p-4">
                <div class="text-xs text-gray-500">Total Saldo Akhir</div>
                <div class="text-lg font-semibold">{{ number_format($sumClosing, 2, ',', '.') }}
                    @if ($sumClosingSide !== '')
                        <span class="chip">{{ strtoupper($sumClosingSide) }}</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="bg-white border rounded">
            <div class="table-wrap">
                <table class="sl-table min-w-full text-sm">
                    <thead>
                        <tr class="text-left border-b">
                            <th class="p-3 w-14">No</th>
                            <th class="p-3 w-32">Kode BP</th>
                            <th class="p-3">Nama BP</th>
                            <th class="p-3 text-right w-40">Saldo Awal</th>
                            <th class="p-3 text-right w-32">Mutasi (D)</th>
                            <th class="p-3 text-right w-32">Mutasi (K)</th>
                            <th class="p-3 text-right w-40">Saldo Akhir</th>
                            <th class="p-3 w-24">Action</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y">
                        @forelse ($items as $idx => $it)
                            @php
                                $bpId = (string) (data_get($it, 'bp_id') ?? data_get($it, 'bp.id') ?? data_get($it, 'id') ?? '');
                                $bpCode = (string) (data_get($it, 'bp_code') ?? data_get($it, 'bp.code') ?? data_get($it, 'code') ?? '');
                                $bpName = (string) (data_get($it, 'bp_name') ?? data_get($it, 'bp.name') ?? data_get($it, 'name') ?? '');

                                $openingAmt = (float) (data_get($it, 'opening_balance') ?? data_get($it, 'opening.amount') ?? data_get($it, 'opening_amount') ?? 0);
                                $openingSide = (string) (data_get($it, 'opening_pos') ?? data_get($it, 'opening.side') ?? data_get($it, 'opening_side') ?? '');

                                $mutDebit = (float) (data_get($it, 'mutation.debit') ?? data_get($it, 'mutation_debit') ?? data_get($it, 'period_debit') ?? 0);
                                $mutCredit = (float) (data_get($it, 'mutation.credit') ?? data_get($it, 'mutation_credit') ?? data_get($it, 'period_credit') ?? 0);

                                $closingAmt = (float) (data_get($it, 'closing_balance') ?? data_get($it, 'closing.amount') ?? data_get($it, 'closing_amount') ?? 0);
                                $closingSide = (string) (data_get($it, 'closing_pos') ?? data_get($it, 'closing.side') ?? data_get($it, 'closing_side') ?? '');

                                $detailQuery = [
                                    'year' => $year,
                                    'from_date' => $fromDate,
                                    'to_date' => $toDate,
                                    'account_id' => $accountId,
                                    'q' => $q,
                                    'include_zero' => $includeZero ? '1' : '0',
                                    'page' => (string) data_get($meta, 'page', $page),
                                    'limit' => (string) data_get($meta, 'limit', $limit),
                                ];
                            @endphp

                            <tr>
                                <td class="p-3 text-gray-600">{{ (int) $idx + 1 }}</td>
                                <td class="p-3 whitespace-nowrap">{{ $bpCode }}</td>
                                <td class="p-3">{{ $bpName }}</td>
                                <td class="p-3 text-right">
                                    {{ number_format($openingAmt, 2, ',', '.') }}
                                    @if ($openingSide !== '')
                                        <span class="chip">{{ strtoupper($openingSide) }}</span>
                                    @endif
                                </td>
                                <td class="p-3 text-right">{{ number_format($mutDebit, 2, ',', '.') }}</td>
                                <td class="p-3 text-right">{{ number_format($mutCredit, 2, ',', '.') }}</td>
                                <td class="p-3 text-right font-semibold">
                                    {{ number_format($closingAmt, 2, ',', '.') }}
                                    @if ($closingSide !== '')
                                        <span class="chip">{{ strtoupper($closingSide) }}</span>
                                    @endif
                                </td>
                                <td class="p-3">
                                    @if ($bpId !== '')
                                        <a class="px-3 py-1 rounded border hover:bg-gray-50"
                                            href="{{ route('finance.subledgers.show', $bpId) }}?{{ http_build_query($detailQuery) }}">
                                            Detail
                                        </a>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="p-4 text-gray-500">No data</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @php
            $cur = (int) data_get($meta, 'page', $page);
            $totalPages = (int) data_get($meta, 'total_pages', 1);
            $prevUrl = $cur > 1 ? request()->fullUrlWithQuery(['page' => $cur - 1]) : null;
            $nextUrl = $cur < $totalPages ? request()->fullUrlWithQuery(['page' => $cur + 1]) : null;
        @endphp

        @if ($totalPages > 1)
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-600">
                    Page {{ $cur }} of {{ $totalPages }} · Total {{ (int) data_get($meta, 'total', 0) }}
                </div>
                <div class="flex gap-2">
                    <a class="px-3 py-2 rounded border {{ $prevUrl ? 'hover:bg-gray-50' : 'text-gray-400 pointer-events-none' }}"
                        href="{{ $prevUrl ?: '#' }}">Prev</a>
                    <a class="px-3 py-2 rounded border {{ $nextUrl ? 'hover:bg-gray-50' : 'text-gray-400 pointer-events-none' }}"
                        href="{{ $nextUrl ?: '#' }}">Next</a>
                </div>
            </div>
        @endif
    @endif
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const search = document.getElementById('accountSearch');
            const select = document.getElementById('accountSelect');

            if (search && select) {
                const allOptions = Array.from(select.querySelectorAll('option')).map(opt => ({
                    el: opt,
                    label: (opt.getAttribute('data-label') || opt.textContent || '').toLowerCase(),
                    value: opt.value
                }));

                function applyFilter(q) {
                    const qq = (q || '').trim().toLowerCase();
                    allOptions.forEach(o => {
                        if (!o.value) {
                            o.el.hidden = false;
                            return;
                        }
                        o.el.hidden = qq ? !o.label.includes(qq) : false;
                    });
                }

                search.addEventListener('input', () => applyFilter(search.value));
                applyFilter('');
            }
        });
    </script>
@endsection


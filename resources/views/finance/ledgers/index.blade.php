@extends('finance.report-layout')

@section('title', 'Buku Besar')
@section('subtitle', 'General Ledger (saldo berjalan)')

@section('header_actions')
    <a class="px-4 py-2 rounded border" href="{{ route('finance.ledgers.index', ['year' => $year]) }}">Reset</a>
@endsection

@section('header_meta')
    @if (!empty($ledger))
        @php
            $acc = $ledger['account'] ?? [];
            $normal = strtoupper((string) ($acc['normal_balance'] ?? ''));
            $accCode = $acc['code'] ?? '';
            $accName = $acc['name'] ?? '';
        @endphp

        <span class="report-chip">Akun: <span class="font-semibold">{{ $accCode }} — {{ $accName }}</span></span>
        <span class="report-chip">Normal: <span class="font-semibold">{{ $normal }}</span></span>
        <span class="report-chip">Periode: <span class="font-semibold">{{ $fromDate }} → {{ $toDate }}</span></span>
    @else
        <span class="report-chip">Periode default: <span class="font-semibold">{{ $fromDate }} →
                {{ $toDate }}</span></span>
    @endif
@endsection

@section('tools')
    <style>
        /* Scoped styles for this page */
        .ledger-card {
            background: #fff;
            border: 1px solid rgba(0, 0, 0, .08);
            border-radius: 12px;
            padding: 14px;
        }

        .ledger-kpi {
            border: 1px solid rgba(0, 0, 0, .08);
            border-radius: 12px;
            padding: 10px 12px;
            background: #fff;
        }

        .ledger-kpi .label {
            font-size: 12px;
            color: #6b7280;
        }

        .ledger-kpi .value {
            font-weight: 700;
            color: #111827;
            margin-top: 2px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            border: 1px solid rgba(0, 0, 0, .08);
            background: #fff;
            color: #374151;
        }

        .badge-opening {
            background: #ecfdf5;
            border-color: #bbf7d0;
            color: #166534;
        }

        .badge-normal {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #1d4ed8;
        }

        .mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        }

        .table-wrap {
            max-height: 70vh;
            overflow: auto;
        }

        .ledger-table thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #f9fafb;
        }

        .ledger-table tbody tr:hover {
            background: #fafafa;
        }
    </style>

    {{-- FILTER FORM (sticky in header area via report-layout) --}}
    <form method="GET" action="{{ route('finance.ledgers.index') }}" class="bg-white border rounded p-4"
        id="ledgerFilterForm">
        @if (!empty($accountsError))
            <div class="p-3 rounded bg-yellow-100 text-yellow-800 mb-3">
                {{ $accountsError }}
            </div>
        @endif

        <div class="grid md:grid-cols-4 gap-3">
            <div class="md:col-span-2">
                <label class="block text-sm mb-1">Akun</label>

                {{-- quick search (filter option list) --}}
                <input id="accountSearch" type="text" class="border rounded px-3 py-2 w-full mb-2"
                    placeholder="Cari akun (code / name)..." autocomplete="off">

                <select id="accountSelect" name="account_id" class="border rounded px-3 py-2 w-full" required>
                    <option value="">— pilih akun —</option>
                    @foreach ($accounts as $a)
                        <option value="{{ $a['id'] }}"
                            data-label="{{ strtolower(($a['code'] ?? '') . ' ' . ($a['name'] ?? '')) }}"
                            {{ $accountId === $a['id'] ? 'selected' : '' }}>
                            {{ $a['code'] }} — {{ $a['name'] }}
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">Tips: pakai akun postable (detail), bukan header.</p>
            </div>

            <div>
                <label class="block text-sm mb-1">Dari tanggal</label>
                <input type="date" name="from_date" value="{{ $fromDate }}" class="border rounded px-3 py-2 w-full"
                    required>
            </div>

            <div>
                <label class="block text-sm mb-1">Sampai tanggal</label>
                <input type="date" name="to_date" value="{{ $toDate }}" class="border rounded px-3 py-2 w-full"
                    required>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2">
            <button class="px-4 py-2 rounded bg-gray-900 text-white">Tampilkan</button>
            <a class="px-4 py-2 rounded border" href="{{ route('finance.ledgers.index', ['year' => $year]) }}">Reset</a>

            {{-- quick presets --}}
            <button type="button" id="presetYear" class="px-3 py-2 rounded border text-sm">Tahun ini</button>
            <button type="button" id="presetMonth" class="px-3 py-2 rounded border text-sm">Bulan ini</button>

            <span class="ml-auto text-xs text-gray-500">
                Periode: <span class="mono">{{ $fromDate }}</span> → <span class="mono">{{ $toDate }}</span>
            </span>
        </div>
    </form>
@endsection

@section('content')
    {{-- API error (bukan validation error bag) --}}
    @if (!empty($apiError))
        <div class="p-3 rounded bg-red-100 text-red-800 border border-red-200">
            {{ $apiError }}
        </div>
    @endif

    @if (!$accountId)
        <div class="ledger-card">
            <div class="p-3 rounded bg-gray-50 border text-gray-700">
                Pilih akun dulu untuk melihat Buku Besar.
            </div>
        </div>
    @elseif(empty($ledger))
        <div class="ledger-card">
            <div class="p-3 rounded bg-gray-50 border text-gray-700">
                Data ledger tidak ditemukan.
            </div>
        </div>
    @else
        @php
            $acc = $ledger['account'] ?? [];
            $opening = $ledger['opening'] ?? [];
            $closing = $ledger['closing'] ?? [];
            $totals = $ledger['totals'] ?? [];
            $rows = $ledger['rows'] ?? [];

            $normal = strtoupper((string) ($acc['normal_balance'] ?? ''));

            $openingBal = (float) ($opening['balance'] ?? 0);
            $openingPos = strtoupper((string) ($opening['pos'] ?? ''));

            $closingBal = (float) ($closing['balance'] ?? 0);
            $closingPos = strtoupper((string) ($closing['pos'] ?? ''));

            $periodDebit = (float) ($totals['period_debit'] ?? 0);
            $periodCredit = (float) ($totals['period_credit'] ?? 0);
        @endphp

        {{-- KPI SUMMARY --}}
        <div class="grid md:grid-cols-5 gap-3">
            <div class="ledger-kpi md:col-span-2 bg-gray-50">
                <div class="label">Saldo Awal</div>
                <div class="value">
                    {{ number_format($openingBal, 2, ',', '.') }}
                    <span class="text-xs text-gray-500">({{ $openingPos }})</span>
                </div>
            </div>

            <div class="ledger-kpi">
                <div class="label">Total Debet Periode</div>
                <div class="value">{{ number_format($periodDebit, 2, ',', '.') }}</div>
            </div>

            <div class="ledger-kpi">
                <div class="label">Total Kredit Periode</div>
                <div class="value">{{ number_format($periodCredit, 2, ',', '.') }}</div>
            </div>

            <div class="ledger-kpi bg-gray-50">
                <div class="label">Saldo Akhir</div>
                <div class="value">
                    {{ number_format($closingBal, 2, ',', '.') }}
                    <span class="text-xs text-gray-500">({{ $closingPos }})</span>
                </div>
            </div>
        </div>

        {{-- TABLE --}}
        <div class="ledger-card">
            <div class="border rounded table-wrap">
                <table class="ledger-table w-full text-sm">
                    <thead>
                        <tr class="text-gray-700">
                            <th class="text-left p-3 w-16">No</th>
                            <th class="text-left p-3 w-36">Tanggal</th>
                            <th class="text-left p-3 w-64">Bukti</th>
                            <th class="text-left p-3">Keterangan</th>
                            <th class="text-right p-3 w-40">Debet</th>
                            <th class="text-right p-3 w-40">Kredit</th>
                            <th class="text-right p-3 w-56">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $txNo = 0; @endphp

                        @forelse ($rows as $r)
                            @php
                                $isOpening = ($r['kind'] ?? '') === 'opening';

                                $debit = (float) ($r['debit'] ?? 0);
                                $credit = (float) ($r['credit'] ?? 0);

                                $saldo = (float) ($r['running_balance'] ?? 0);
                                $pos = strtoupper((string) ($r['running_pos'] ?? ''));

                                $date = $r['date'] ?? '-';
                                $desc = $r['description'] ?? '';
                                $entryId = $r['entry_id'] ?? null;
                                $ref = $r['ref'] ?? ($entryId ?? '-');

                                $etype = strtolower((string) ($r['entry_type'] ?? ''));

                                if (!$isOpening) {
                                    $txNo++;
                                }
                            @endphp

                            <tr class="border-t {{ $isOpening ? 'bg-green-50' : '' }}">
                                <td class="p-3">
                                    @if ($isOpening)
                                        <span class="badge badge-opening">OPEN</span>
                                    @else
                                        {{ $txNo }}
                                    @endif
                                </td>

                                <td class="p-3 mono">{{ $date }}</td>

                                <td class="p-3">
                                    @if ($entryId)
                                        <a class="underline mono"
                                            href="{{ route('finance.journal_entries.edit', $entryId) }}">
                                            {{ $ref }}
                                        </a>
                                        @if ($etype)
                                            <span
                                                class="ml-2 badge {{ $etype === 'opening' ? 'badge-opening' : 'badge-normal' }}">
                                                {{ strtoupper($etype) }}
                                            </span>
                                        @endif
                                    @else
                                        <span class="mono">{{ $ref }}</span>
                                    @endif
                                </td>

                                <td class="p-3">
                                    @if ($isOpening)
                                        <span class="font-semibold">Saldo awal</span>
                                        <span class="text-xs text-gray-500">(sebelum periode)</span>
                                    @else
                                        {{ $desc }}
                                    @endif
                                </td>

                                <td class="p-3 text-right">{{ number_format($debit, 2, ',', '.') }}</td>
                                <td class="p-3 text-right">{{ number_format($credit, 2, ',', '.') }}</td>

                                <td class="p-3 text-right font-semibold">
                                    {{ number_format($saldo, 2, ',', '.') }}
                                    <span class="text-xs text-gray-500">{{ $pos }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-4 text-gray-500">No data</td>
                            </tr>
                        @endforelse
                    </tbody>

                    @if (!empty($rows))
                        <tfoot class="bg-gray-50 border-t">
                            <tr>
                                <td colspan="4" class="p-3 font-semibold text-right">TOTAL PERIODE</td>
                                <td class="p-3 text-right font-semibold">{{ number_format($periodDebit, 2, ',', '.') }}
                                </td>
                                <td class="p-3 text-right font-semibold">{{ number_format($periodCredit, 2, ',', '.') }}
                                </td>
                                <td class="p-3 text-right font-semibold">
                                    {{ number_format($closingBal, 2, ',', '.') }}
                                    <span class="text-xs text-gray-500">{{ $closingPos }}</span>
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    @endif
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- account search filter ---
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
                        } // placeholder always visible
                        o.el.hidden = qq ? !o.label.includes(qq) : false;
                    });
                }

                search.addEventListener('input', () => applyFilter(search.value));
                applyFilter('');
            }

            // --- quick preset dates ---
            const form = document.getElementById('ledgerFilterForm');
            const btnYear = document.getElementById('presetYear');
            const btnMonth = document.getElementById('presetMonth');

            const fromInput = form?.querySelector('input[name="from_date"]');
            const toInput = form?.querySelector('input[name="to_date"]');

            function pad2(n) {
                return String(n).padStart(2, '0');
            }

            function fmtDate(d) {
                return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
            }

            if (btnYear && fromInput && toInput) {
                btnYear.addEventListener('click', function() {
                    const now = new Date();
                    const y = now.getFullYear();
                    fromInput.value = `${y}-01-01`;
                    toInput.value = `${y}-12-31`;
                });
            }

            if (btnMonth && fromInput && toInput) {
                btnMonth.addEventListener('click', function() {
                    const now = new Date();
                    const y = now.getFullYear();
                    const m = now.getMonth();

                    const first = new Date(y, m, 1);
                    const last = new Date(y, m + 1, 0);

                    fromInput.value = fmtDate(first);
                    toInput.value = fmtDate(last);
                });
            }
        });
    </script>
@endsection

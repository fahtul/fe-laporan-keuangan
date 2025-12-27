@extends('finance.report-layout')

@php
    $periodFrom = (string) data_get($period, 'from_date', $fromDate);
    $periodTo = (string) data_get($period, 'to_date', $toDate);
    $hasItems = !empty($items) && count($items) > 0;
    $hasVirtual = !empty($virtualRows) && count($virtualRows) > 0;

    $netProfit = (float) data_get($totals, 'net_profit', 0);
    $profitPos = strtolower((string) data_get($totals, 'net_profit_pos', ''));
    $isLoss = $netProfit < 0 || $profitPos === 'loss';
    $netBadgeClass = $isLoss ? 'bg-red-50 text-red-700 border-red-200' : 'bg-green-50 text-green-700 border-green-200';
@endphp

@section('title', 'Neraca Lajur')
@section('subtitle', 'Worksheet (Neraca Awal, Mutasi, Saldo, L/R, Neraca Akhir)')

@section('header_actions')
    <a class="px-4 py-2 rounded border bg-white text-gray-900 hover:bg-gray-50"
        href="{{ route('finance.worksheet.index', ['year' => $year]) }}">Reset</a>
@endsection

@section('header_meta')
    <span class="report-chip">Periode: <span class="font-semibold">{{ $periodFrom }} - {{ $periodTo }}</span></span>
    <span class="report-chip">Include zero: <span class="font-semibold">{{ $includeZero ? 'Ya' : 'Tidak' }}</span></span>
    <span class="report-chip">Include header: <span class="font-semibold">{{ $includeHeader ? 'Ya' : 'Tidak' }}</span></span>
    <span class="report-chip">Virtual profit: <span class="font-semibold">{{ $includeVirtualProfit ? 'Ya' : 'Tidak' }}</span></span>
    @if (!empty($totals))
        <span class="report-chip border {{ $netBadgeClass }}">
            {{ $isLoss ? 'LOSS' : 'PROFIT' }}: <span class="font-semibold">{{ number_format($netProfit, 2, ',', '.') }}</span>
        </span>
    @endif
@endsection

@section('tools')
    <style>
        .ws-wrap {
            max-height: 70vh;
            overflow: auto;
        }

        .ws-table thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #f9fafb;
        }

        .ws-num {
            text-align: right;
            white-space: nowrap;
        }

        .ws-name {
            min-width: 280px;
        }

        .ws-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 10px;
            border-radius: 999px;
            font-size: 12px;
            border: 1px solid rgba(0, 0, 0, .08);
            background: #fff;
            color: #374151;
        }
    </style>

    <div class="bg-white border rounded p-4 space-y-3">
        <form method="GET" action="{{ route('finance.worksheet.index') }}" class="space-y-3">
            <input type="hidden" name="year" value="{{ $year }}">

            <div class="grid md:grid-cols-4 gap-3">
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

                <div class="flex items-end">
                    <input type="hidden" name="include_zero" value="0">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="include_zero" value="1" class="rounded border-gray-300"
                            {{ $includeZero ? 'checked' : '' }}>
                        <span class="text-sm">Include zero</span>
                    </label>
                </div>

                <div class="flex items-end">
                    <input type="hidden" name="include_header" value="0">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="include_header" value="1" class="rounded border-gray-300"
                            {{ $includeHeader ? 'checked' : '' }}>
                        <span class="text-sm">Include header</span>
                    </label>
                </div>
            </div>

            <div class="grid md:grid-cols-4 gap-3">
                <div class="flex items-end">
                    <input type="hidden" name="include_virtual_profit" value="0">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="include_virtual_profit" value="1" class="rounded border-gray-300"
                            {{ $includeVirtualProfit ? 'checked' : '' }}>
                        <span class="text-sm">Virtual profit</span>
                    </label>
                </div>

                <div class="flex items-end">
                    <input type="hidden" name="use_code_rule" value="0">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="use_code_rule" value="1" class="rounded border-gray-300"
                            {{ $useCodeRule ? 'checked' : '' }}>
                        <span class="text-sm">Use code rule</span>
                    </label>
                </div>

                <div class="md:col-span-2 flex items-end gap-2 flex-wrap">
                    <button type="submit" class="px-4 py-2 rounded bg-indigo-600 text-white">Tampilkan</button>
                    <button type="submit" name="preset" value="year"
                        class="px-4 py-2 rounded border bg-white text-gray-900 hover:bg-gray-50">
                        Tahun ini
                    </button>
                    <button type="submit" name="preset" value="month"
                        class="px-4 py-2 rounded border bg-white text-gray-900 hover:bg-gray-50">
                        Bulan ini
                    </button>
                    <a class="px-4 py-2 rounded border bg-white text-gray-900 hover:bg-gray-50"
                        href="{{ route('finance.worksheet.index', ['year' => $year]) }}">Reset</a>
                </div>
            </div>
        </form>

        <div>
            <label class="block text-sm mb-1">Cari di tabel (client-side)</label>
            <input id="wsSearch" type="text" class="border rounded px-3 py-2 w-full"
                placeholder="ketik code / name..." autocomplete="off">
        </div>
    </div>
@endsection

@section('content')
    @if (!empty($apiError))
        <div class="p-3 rounded bg-yellow-100 text-yellow-800">
            {{ $apiError }}
        </div>
    @endif

    <div class="bg-white border rounded">
        <div class="ws-wrap">
            <table class="ws-table min-w-full text-sm" id="wsTable">
                <thead>
                    <tr class="text-left border-b">
                        <th class="p-3 w-28">Code</th>
                        <th class="p-3 ws-name">Name</th>
                        <th class="p-3 w-28">Pos</th>

                        <th class="p-3 ws-num w-36">Opening D</th>
                        <th class="p-3 ws-num w-36">Opening K</th>

                        <th class="p-3 ws-num w-36">Mutation D</th>
                        <th class="p-3 ws-num w-36">Mutation K</th>

                        <th class="p-3 ws-num w-36">Closing D</th>
                        <th class="p-3 ws-num w-36">Closing K</th>

                        <th class="p-3 ws-num w-36">L/R D</th>
                        <th class="p-3 ws-num w-36">L/R K</th>

                        <th class="p-3 ws-num w-36">Final D</th>
                        <th class="p-3 ws-num w-36">Final K</th>
                    </tr>
                </thead>

                <tbody class="divide-y" id="wsTbody">
                    @php
                        $rowCount = 0;
                    @endphp

                    @forelse ($items as $it)
                        @php
                            $code = (string) data_get($it, 'code', '');
                            $name = (string) data_get($it, 'name', '');
                            $type = (string) (data_get($it, 'type') ?? data_get($it, 'account_type') ?? '');
                            $isPostable = (bool) data_get($it, 'is_postable', true);
                            $pos = (string) (data_get($it, 'normal_pos') ?? data_get($it, 'pos') ?? data_get($it, 'normal_balance') ?? '');

                            $openingD = (float) data_get($it, 'opening.debit', data_get($it, 'opening_debit', 0));
                            $openingK = (float) data_get($it, 'opening.credit', data_get($it, 'opening_credit', 0));
                            $mutD = (float) data_get($it, 'mutation.debit', data_get($it, 'mutation_debit', 0));
                            $mutK = (float) data_get($it, 'mutation.credit', data_get($it, 'mutation_credit', 0));
                            $closingD = (float) data_get($it, 'closing.debit', data_get($it, 'closing_debit', 0));
                            $closingK = (float) data_get($it, 'closing.credit', data_get($it, 'closing_credit', 0));
                            $pnlD = (float) data_get($it, 'pnl.debit', data_get($it, 'pnl_debit', 0));
                            $pnlK = (float) data_get($it, 'pnl.credit', data_get($it, 'pnl_credit', 0));
                            $finalD = (float) data_get($it, 'final.debit', data_get($it, 'final_debit', 0));
                            $finalK = (float) data_get($it, 'final.credit', data_get($it, 'final_credit', 0));

                            $isHeader = !$isPostable;
                            $isPnL = in_array($type, ['revenue', 'expense'], true);
                            $rowClass = $isHeader ? 'bg-gray-50 font-semibold' : '';

                            $searchText = strtolower(trim($code . ' ' . $name));
                            $rowCount++;
                        @endphp

                        <tr class="{{ $rowClass }}" data-search="{{ e($searchText) }}">
                            <td class="p-3 whitespace-nowrap">{{ $code }}</td>
                            <td class="p-3">
                                <div class="flex items-center gap-2">
                                    <span class="truncate">{{ $name }}</span>
                                    @if ($isPnL)
                                        <span class="ws-badge border bg-blue-50 text-blue-700 border-blue-200">P&amp;L</span>
                                    @endif
                                </div>
                            </td>
                            <td class="p-3 whitespace-nowrap">{{ $pos ?: '-' }}</td>

                            <td class="p-3 ws-num">{{ number_format($openingD, 2, ',', '.') }}</td>
                            <td class="p-3 ws-num">{{ number_format($openingK, 2, ',', '.') }}</td>

                            <td class="p-3 ws-num">{{ number_format($mutD, 2, ',', '.') }}</td>
                            <td class="p-3 ws-num">{{ number_format($mutK, 2, ',', '.') }}</td>

                            <td class="p-3 ws-num">{{ number_format($closingD, 2, ',', '.') }}</td>
                            <td class="p-3 ws-num">{{ number_format($closingK, 2, ',', '.') }}</td>

                            <td class="p-3 ws-num">{{ number_format($pnlD, 2, ',', '.') }}</td>
                            <td class="p-3 ws-num">{{ number_format($pnlK, 2, ',', '.') }}</td>

                            <td class="p-3 ws-num">{{ number_format($finalD, 2, ',', '.') }}</td>
                            <td class="p-3 ws-num">{{ number_format($finalK, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="p-4 text-gray-500">No data</td>
                        </tr>
                    @endforelse

                    @if ($hasVirtual)
                        @foreach ($virtualRows as $vr)
                            @php
                                $code = (string) data_get($vr, 'code', '');
                                $name = (string) data_get($vr, 'name', 'VIRTUAL');
                                $pos = (string) (data_get($vr, 'pos') ?? data_get($vr, 'normal_pos') ?? '');

                                $finalD = (float) data_get($vr, 'final.debit', data_get($vr, 'final_debit', 0));
                                $finalK = (float) data_get($vr, 'final.credit', data_get($vr, 'final_credit', 0));

                                $searchText = strtolower(trim($code . ' ' . $name));
                            @endphp
                            <tr class="bg-green-50" data-search="{{ e($searchText) }}">
                                <td class="p-3 whitespace-nowrap">{{ $code ?: '-' }}</td>
                                <td class="p-3 font-semibold">{{ $name }}</td>
                                <td class="p-3 whitespace-nowrap">{{ $pos ?: '-' }}</td>

                                <td class="p-3 ws-num">0,00</td>
                                <td class="p-3 ws-num">0,00</td>

                                <td class="p-3 ws-num">0,00</td>
                                <td class="p-3 ws-num">0,00</td>

                                <td class="p-3 ws-num">0,00</td>
                                <td class="p-3 ws-num">0,00</td>

                                <td class="p-3 ws-num">0,00</td>
                                <td class="p-3 ws-num">0,00</td>

                                <td class="p-3 ws-num font-semibold">{{ number_format($finalD, 2, ',', '.') }}</td>
                                <td class="p-3 ws-num font-semibold">{{ number_format($finalK, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>

                @if (!empty($totals))
                    @php
                        $tOpenD = (float) data_get($totals, 'opening.debit', data_get($totals, 'opening_debit', 0));
                        $tOpenK = (float) data_get($totals, 'opening.credit', data_get($totals, 'opening_credit', 0));
                        $tMutD = (float) data_get($totals, 'mutation.debit', data_get($totals, 'mutation_debit', 0));
                        $tMutK = (float) data_get($totals, 'mutation.credit', data_get($totals, 'mutation_credit', 0));
                        $tCloseD = (float) data_get($totals, 'closing.debit', data_get($totals, 'closing_debit', 0));
                        $tCloseK = (float) data_get($totals, 'closing.credit', data_get($totals, 'closing_credit', 0));
                        $tPnlD = (float) data_get($totals, 'pnl.debit', data_get($totals, 'pnl_debit', 0));
                        $tPnlK = (float) data_get($totals, 'pnl.credit', data_get($totals, 'pnl_credit', 0));
                        $tFinalD = (float) data_get($totals, 'final.debit', data_get($totals, 'final_debit', 0));
                        $tFinalK = (float) data_get($totals, 'final.credit', data_get($totals, 'final_credit', 0));
                    @endphp
                    <tfoot class="bg-gray-50 border-t">
                        <tr>
                            <td class="p-3 font-semibold" colspan="3">TOTAL</td>

                            <td class="p-3 ws-num font-semibold">{{ number_format($tOpenD, 2, ',', '.') }}</td>
                            <td class="p-3 ws-num font-semibold">{{ number_format($tOpenK, 2, ',', '.') }}</td>

                            <td class="p-3 ws-num font-semibold">{{ number_format($tMutD, 2, ',', '.') }}</td>
                            <td class="p-3 ws-num font-semibold">{{ number_format($tMutK, 2, ',', '.') }}</td>

                            <td class="p-3 ws-num font-semibold">{{ number_format($tCloseD, 2, ',', '.') }}</td>
                            <td class="p-3 ws-num font-semibold">{{ number_format($tCloseK, 2, ',', '.') }}</td>

                            <td class="p-3 ws-num font-semibold">{{ number_format($tPnlD, 2, ',', '.') }}</td>
                            <td class="p-3 ws-num font-semibold">{{ number_format($tPnlK, 2, ',', '.') }}</td>

                            <td class="p-3 ws-num font-semibold">{{ number_format($tFinalD, 2, ',', '.') }}</td>
                            <td class="p-3 ws-num font-semibold">{{ number_format($tFinalK, 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        (function() {
            const input = document.getElementById('wsSearch');
            const tbody = document.getElementById('wsTbody');
            if (!input || !tbody) return;

            function apply() {
                const q = (input.value || '').trim().toLowerCase();
                const rows = Array.from(tbody.querySelectorAll('tr[data-search]'));
                rows.forEach(r => {
                    const text = (r.getAttribute('data-search') || '');
                    r.style.display = q === '' || text.includes(q) ? '' : 'none';
                });
            }

            input.addEventListener('input', apply);
            apply();
        })();
    </script>
@endsection


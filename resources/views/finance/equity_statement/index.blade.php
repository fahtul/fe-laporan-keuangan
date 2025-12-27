@extends('finance.report-layout')

@php
    $periodFrom = (string) data_get($period, 'from_date', $fromDate);
    $periodTo = (string) data_get($period, 'to_date', $toDate);

    $profitModeLabel = $profitMode === 'after_tax' ? 'AFTER TAX' : 'NET';

    $openingItems = data_get($opening, 'items', []) ?: [];
    $openingTotalAmount = (float) data_get($opening, 'total.amount', data_get($opening, 'total', 0));
    $openingTotalSide = (string) data_get($opening, 'total.side', data_get($opening, 'side', ''));

    $closingItems = data_get($closing, 'items', []) ?: [];
    $closingTotalAmount = (float) data_get($closing, 'total.amount', data_get($closing, 'total', 0));
    $closingTotalSide = (string) data_get($closing, 'total.side', data_get($closing, 'side', ''));

    $categories = data_get($movements, 'categories', []) ?: [];

    $profitAmount = null;
    $profitSide = null;
    foreach ($categories as $cat) {
        $key = strtolower((string) data_get($cat, 'key', data_get($cat, 'type', '')));
        if ($key === 'profit') {
            $profitAmount = (float) data_get($cat, 'total.amount', data_get($cat, 'amount', 0));
            $profitSide = (string) data_get($cat, 'total.side', data_get($cat, 'side', ''));
            break;
        }
    }

    $profitIsLoss = is_numeric($profitAmount) && (float) $profitAmount < 0;
    $profitBadgeClass = $profitIsLoss ? 'bg-red-50 text-red-700 border-red-200' : 'bg-green-50 text-green-700 border-green-200';
@endphp

@section('title', 'LP Equitas')
@section('subtitle', 'Laporan Perubahan Equitas')

@section('header_actions')
    <a class="px-4 py-2 rounded border bg-white text-gray-900 hover:bg-gray-50"
        href="{{ route('finance.equity_statement.index', ['year' => $year]) }}">Reset</a>
    <a class="px-4 py-2 rounded border bg-white text-gray-900 hover:bg-gray-50"
        href="{{ route('finance.balance_sheet.index', ['year' => $year]) }}">Buka Neraca</a>
    <a class="px-4 py-2 rounded border bg-white text-gray-900 hover:bg-gray-50"
        href="{{ route('finance.income_statement.index', ['year' => $year]) }}">Buka Laba Rugi</a>
@endsection

@section('header_meta')
    <span class="report-chip">Periode: <span class="font-semibold">{{ $periodFrom }} - {{ $periodTo }}</span></span>
    <span class="report-chip">Profit mode: <span class="font-semibold">{{ $profitModeLabel }}</span></span>
    <span class="report-chip">Virtual profit: <span class="font-semibold">{{ $includeVirtualProfit ? 'Ya' : 'Tidak' }}</span></span>
    <span class="report-chip">Include header: <span class="font-semibold">{{ $includeHeader ? 'Ya' : 'Tidak' }}</span></span>
    <span class="report-chip">Include zero: <span class="font-semibold">{{ $includeZero ? 'Ya' : 'Tidak' }}</span></span>
    @if (!is_null($profitAmount))
        <span class="report-chip border {{ $profitBadgeClass }}">
            Net Profit: <span class="font-semibold">{{ number_format((float) $profitAmount, 2, ',', '.') }}</span>
        </span>
    @endif
@endsection

@section('tools')
    <style>
        .sec-title {
            background: #16a34a;
            color: #fff;
            font-weight: 700;
            padding: 10px 12px;
            border-radius: 8px 8px 0 0;
        }

        .money {
            text-align: right;
            white-space: nowrap;
        }

        .side {
            font-size: 11px;
            color: #6b7280;
            margin-left: 6px;
        }
    </style>

    <form method="GET" action="{{ route('finance.equity_statement.index') }}" class="bg-white border rounded p-4">
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

            <div>
                <label class="block text-sm mb-1">Profit mode</label>
                <select name="profit_mode" class="border rounded px-3 py-2 w-full">
                    <option value="net" {{ $profitMode === 'net' ? 'selected' : '' }}>net</option>
                    <option value="after_tax" {{ $profitMode === 'after_tax' ? 'selected' : '' }}>after_tax</option>
                </select>
            </div>

            <div class="flex items-end">
                <input type="hidden" name="include_virtual_profit" value="0">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="include_virtual_profit" value="1" class="rounded border-gray-300"
                        {{ $includeVirtualProfit ? 'checked' : '' }}>
                    <span class="text-sm">Virtual profit</span>
                </label>
            </div>
        </div>

        <div class="mt-3 grid md:grid-cols-4 gap-3">
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

            <div class="flex items-end">
                <input type="hidden" name="use_code_rule" value="0">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="use_code_rule" value="1" class="rounded border-gray-300"
                        {{ $useCodeRule ? 'checked' : '' }}>
                    <span class="text-sm">Use code rule</span>
                </label>
            </div>

            <div class="flex items-end gap-2 flex-wrap">
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
                    href="{{ route('finance.equity_statement.index', ['year' => $year]) }}">Reset</a>
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

    <div class="grid lg:grid-cols-2 gap-4">
        <div class="bg-white border rounded overflow-hidden">
            <div class="sec-title">EQUITAS AWAL</div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left border-b">
                            <th class="p-3 w-28">Kode Akun</th>
                            <th class="p-3">Nama Akun</th>
                            <th class="p-3 w-44 money">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse ($openingItems as $it)
                            @php
                                $amt = (float) data_get($it, 'amount', 0);
                                $side = (string) data_get($it, 'side', '');
                                $neg = $amt < 0;
                            @endphp
                            <tr>
                                <td class="p-3 whitespace-nowrap">{{ data_get($it, 'code') }}</td>
                                <td class="p-3">{{ data_get($it, 'name') }}</td>
                                <td class="p-3 money {{ $neg ? 'text-red-600' : '' }}">
                                    {{ number_format($amt, 2, ',', '.') }}
                                    @if ($side)
                                        <span class="side">({{ strtoupper($side) }})</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="p-4 text-gray-500">No data</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-50 border-t">
                        <tr>
                            <td class="p-3 font-semibold" colspan="2">JUMLAH EQUITAS AWAL PERIODE</td>
                            <td class="p-3 money font-semibold">
                                {{ number_format($openingTotalAmount, 2, ',', '.') }}
                                @if ($openingTotalSide)
                                    <span class="side">({{ strtoupper($openingTotalSide) }})</span>
                                @endif
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="bg-white border rounded overflow-hidden">
            <div class="sec-title">EQUITAS AKHIR</div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left border-b">
                            <th class="p-3 w-28">Kode Akun</th>
                            <th class="p-3">Nama Akun</th>
                            <th class="p-3 w-44 money">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse ($closingItems as $it)
                            @php
                                $amt = (float) data_get($it, 'amount', 0);
                                $side = (string) data_get($it, 'side', '');
                                $neg = $amt < 0;
                            @endphp
                            <tr>
                                <td class="p-3 whitespace-nowrap">{{ data_get($it, 'code') }}</td>
                                <td class="p-3">{{ data_get($it, 'name') }}</td>
                                <td class="p-3 money {{ $neg ? 'text-red-600' : '' }}">
                                    {{ number_format($amt, 2, ',', '.') }}
                                    @if ($side)
                                        <span class="side">({{ strtoupper($side) }})</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="p-4 text-gray-500">No data</td>
                            </tr>
                        @endforelse

                        @if ($includeVirtualProfit && !is_null($profitAmount))
                            <tr class="bg-green-50">
                                <td class="p-3 whitespace-nowrap">-</td>
                                <td class="p-3 font-semibold">Laba periode berjalan</td>
                                <td class="p-3 money font-semibold {{ $profitIsLoss ? 'text-red-700' : 'text-green-700' }}">
                                    {{ number_format((float) $profitAmount, 2, ',', '.') }}
                                    @if ($profitSide)
                                        <span class="side">({{ strtoupper($profitSide) }})</span>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    </tbody>
                    <tfoot class="bg-gray-50 border-t">
                        <tr>
                            <td class="p-3 font-semibold" colspan="2">JUMLAH EQUITAS AKHIR PERIODE</td>
                            <td class="p-3 money font-semibold">
                                {{ number_format($closingTotalAmount, 2, ',', '.') }}
                                @if ($closingTotalSide)
                                    <span class="side">({{ strtoupper($closingTotalSide) }})</span>
                                @endif
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    @if (!empty($categories))
        @php
            $hasMovementDetails = false;
            foreach ($categories as $cat) {
                if (!empty(data_get($cat, 'items', []))) {
                    $hasMovementDetails = true;
                    break;
                }
            }
        @endphp

        @if ($hasMovementDetails)
            <div class="bg-white border rounded overflow-hidden mt-4">
                <div class="sec-title">PERUBAHAN / MOVEMENTS</div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left border-b">
                                <th class="p-3 w-28">Kode</th>
                                <th class="p-3">Nama</th>
                                <th class="p-3 w-44 money">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($categories as $cat)
                                @php
                                    $itemsCat = data_get($cat, 'items', []) ?: [];
                                    $key = strtolower((string) data_get($cat, 'key', data_get($cat, 'type', '')));
                                    $label = (string) data_get($cat, 'label', data_get($cat, 'name', $key));
                                    $isProfit = $key === 'profit';
                                    if ($isProfit) {
                                        $label = 'Laba periode berjalan';
                                    }
                                @endphp

                                @if (!empty($itemsCat))
                                    <tr class="bg-gray-50">
                                        <td class="p-3 font-semibold" colspan="3">{{ $label }}</td>
                                    </tr>

                                    @foreach ($itemsCat as $it)
                                        @php
                                            $amt = (float) data_get($it, 'amount', 0);
                                            $side = (string) data_get($it, 'side', '');
                                            $neg = $amt < 0;
                                        @endphp
                                        <tr>
                                            <td class="p-3 whitespace-nowrap">{{ data_get($it, 'code') }}</td>
                                            <td class="p-3">{{ data_get($it, 'name') }}</td>
                                            <td class="p-3 money {{ $neg ? 'text-red-600' : '' }}">
                                                {{ number_format($amt, 2, ',', '.') }}
                                                @if ($side)
                                                    <span class="side">({{ strtoupper($side) }})</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
@endsection

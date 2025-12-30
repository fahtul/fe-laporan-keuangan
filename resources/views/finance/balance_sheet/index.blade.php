@extends('finance.report-layout')

@php
    $yNow = (int) now()->format('Y');
    $fromProfit = (string) data_get($profitPeriod, 'from_date', '');
    $toProfit = (string) data_get($profitPeriod, 'to_date', '');

    $basisLabels = [
        'after_tax' => 'after_tax',
        'operating' => 'operating',
        'net' => 'net',
    ];
    $profitBasisLabel = $basisLabels[$profitBasis] ?? (string) $profitBasis;

    $assets = data_get($sections, 'assets', []) ?: [];
    $liabilities = data_get($sections, 'liabilities', []) ?: [];
    $equity = data_get($sections, 'equity', []) ?: [];
    $currentProfit = data_get($sections, 'current_profit', []) ?: [];

    $assetsItems = data_get($assets, 'items', []) ?: [];
    $liabilityItems = data_get($liabilities, 'items', []) ?: [];
    $equityItems = data_get($equity, 'items', []) ?: [];

    $assetsTotal = (float) data_get($assets, 'total', data_get($totals, 'assets_total', 0));
    $liabilitiesTotal = (float) data_get($liabilities, 'total', data_get($totals, 'liabilities_total', 0));
    $equityTotal = (float) data_get($equity, 'total', data_get($totals, 'equity_total', 0));

    $rhsTotal = (float) data_get($totals, 'liabilities_plus_equity', 0);
    $diffAbs = abs((float) $difference);
    $diffOk = $diffAbs < 0.01;
    $isBalancedOk = $balanced || $diffOk;
    $balanceBadgeClass = $isBalancedOk
        ? 'bg-green-50 text-green-700 border-green-200'
        : 'bg-red-50 text-red-700 border-red-200';

    $diffClass = $diffOk ? 'text-green-700' : 'text-red-700';

    $cpAmount = (float) data_get($currentProfit, 'amount', 0);
    $cpPos = strtoupper((string) data_get($currentProfit, 'pos', ''));
@endphp

@section('title', 'Neraca')
@section('subtitle', 'Balance Sheet (posisi per tanggal)')

@section('header_actions')
    @php
        $exportParams = array_merge([
            'year' => $year,
            'as_of' => $asOf,
            'include_zero' => $includeZero ? '1' : '0',
            'include_header' => $includeHeader ? '1' : '0',
            'profit_basis' => $profitBasis,
        ], request()->query());
    @endphp
    <a class="px-4 py-2 rounded bg-emerald-600 text-white hover:bg-emerald-700"
        href="{{ route('finance.exports.xlsx', array_merge(['report' => 'balance-sheet'], $exportParams)) }}">
        Export Excel
    </a>
    <a class="px-4 py-2 rounded border bg-white"
        href="{{ route('finance.balance_sheet.index', ['year' => $year]) }}">Reset</a>
@endsection

@section('header_meta')
    <span class="report-chip">As of: <span class="font-semibold">{{ $asOf }}</span></span>
    <span class="report-chip">Profit period: <span class="font-semibold">
            {{ $fromProfit && $toProfit ? $fromProfit . ' - ' . $toProfit : '-' }}
        </span></span>
    <span class="report-chip">Include zero: <span class="font-semibold">{{ $includeZero ? 'Ya' : 'Tidak' }}</span></span>
    <span class="report-chip">Include header: <span class="font-semibold">{{ $includeHeader ? 'Ya' : 'Tidak' }}</span></span>
    <span class="report-chip">Profit basis: <span class="font-semibold">{{ $profitBasisLabel }}</span></span>
    <span class="report-chip border {{ $balanceBadgeClass }}">
        {{ $isBalancedOk ? 'BALANCE' : 'NOT BALANCE' }}
        <span class="font-semibold {{ $diffClass }}">({{ number_format((float) $difference, 2, ',', '.') }})</span>
    </span>
@endsection

@section('tools')
    <style>
        .bs-wrap {
            max-height: 70vh;
            overflow: auto;
        }

        .bs-table thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #f9fafb;
        }

        .pos-chip {
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

    <form method="GET" action="{{ route('finance.balance_sheet.index') }}" class="bg-white border rounded p-4">
        <div class="grid md:grid-cols-4 gap-3">
            <div>
                <label class="block text-sm mb-1">Tahun</label>
                <select name="year" class="border rounded px-3 py-2 w-full">
                    @for ($y = $yNow - 5; $y <= $yNow + 1; $y++)
                        <option value="{{ $y }}" {{ (string) $y === (string) $year ? 'selected' : '' }}>
                            {{ $y }}
                        </option>
                    @endfor
                </select>
            </div>

            <div>
                <label class="block text-sm mb-1">As of</label>
                <input type="date" name="as_of" value="{{ $asOf }}" class="border rounded px-3 py-2 w-full" required>
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

        <div class="mt-3 grid md:grid-cols-4 gap-3">
            <div class="md:col-span-2">
                <label class="block text-sm mb-1">Profit basis</label>
                <select name="profit_basis" class="border rounded px-3 py-2 w-full">
                    <option value="after_tax" {{ $profitBasis === 'after_tax' ? 'selected' : '' }}>after_tax</option>
                    <option value="operating" {{ $profitBasis === 'operating' ? 'selected' : '' }}>operating</option>
                    <option value="net" {{ $profitBasis === 'net' ? 'selected' : '' }}>net</option>
                </select>
            </div>

            <div class="md:col-span-2 flex items-end gap-2 flex-wrap">
                <button type="submit" class="px-4 py-2 rounded bg-indigo-600 text-white">Tampilkan</button>
                <button type="submit" name="preset" value="year" class="px-4 py-2 rounded border bg-gray-50 border-gray-300">
                    Tahun ini
                </button>
                <button type="submit" name="preset" value="month" class="px-4 py-2 rounded border bg-gray-50 border-gray-300">
                    Bulan ini
                </button>
                <a class="px-4 py-2 rounded border bg-white"
                    href="{{ route('finance.balance_sheet.index', ['year' => $year]) }}">Reset</a>
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
        <div class="bg-white border rounded">
            <div class="p-3 border-b font-semibold text-gray-900">ASET</div>
            <div class="bs-wrap">
                <table class="bs-table w-full text-sm">
                    <thead>
                        <tr class="text-left border-b">
                            <th class="p-3 w-28">Code</th>
                            <th class="p-3">Name</th>
                            <th class="p-3 text-right w-44">Amount</th>
                            <th class="p-3 w-20">Pos</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse ($assetsItems as $it)
                            @php
                                $amount = (float) data_get($it, 'amount', 0);
                                $pos = strtoupper((string) data_get($it, 'pos', ''));
                                $neg = $amount < 0;
                                $posClass = $pos === 'D' ? 'bg-blue-50 text-blue-700 border-blue-200' : ($pos === 'K' ? 'bg-purple-50 text-purple-700 border-purple-200' : 'bg-gray-50 text-gray-700 border-gray-200');
                            @endphp
                            <tr>
                                <td class="p-3 whitespace-nowrap">{{ data_get($it, 'code') }}</td>
                                <td class="p-3">{{ data_get($it, 'name') }}</td>
                                <td class="p-3 text-right {{ $neg ? 'text-red-600' : '' }}">
                                    {{ number_format($amount, 2, ',', '.') }}
                                </td>
                                <td class="p-3">
                                    <span class="pos-chip border {{ $posClass }}">{{ $pos ?: '-' }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-4 text-gray-500">No data</td>
                            </tr>
                        @endforelse

                        <tr class="bg-gray-50 border-t">
                            <td colspan="2" class="p-3 font-semibold">TOTAL ASET</td>
                            <td class="p-3 text-right font-semibold">{{ number_format($assetsTotal, 2, ',', '.') }}</td>
                            <td class="p-3"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-4">
            <div class="bg-white border rounded">
                <div class="p-3 border-b font-semibold text-gray-900">KEWAJIBAN</div>
                <div class="bs-wrap">
                    <table class="bs-table w-full text-sm">
                        <thead>
                            <tr class="text-left border-b">
                                <th class="p-3 w-28">Code</th>
                                <th class="p-3">Name</th>
                                <th class="p-3 text-right w-44">Amount</th>
                                <th class="p-3 w-20">Pos</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse ($liabilityItems as $it)
                                @php
                                    $amount = (float) data_get($it, 'amount', 0);
                                    $pos = strtoupper((string) data_get($it, 'pos', ''));
                                    $neg = $amount < 0;
                                    $posClass = $pos === 'D' ? 'bg-blue-50 text-blue-700 border-blue-200' : ($pos === 'K' ? 'bg-purple-50 text-purple-700 border-purple-200' : 'bg-gray-50 text-gray-700 border-gray-200');
                                @endphp
                                <tr>
                                    <td class="p-3 whitespace-nowrap">{{ data_get($it, 'code') }}</td>
                                    <td class="p-3">{{ data_get($it, 'name') }}</td>
                                    <td class="p-3 text-right {{ $neg ? 'text-red-600' : '' }}">
                                        {{ number_format($amount, 2, ',', '.') }}
                                    </td>
                                    <td class="p-3">
                                        <span class="pos-chip border {{ $posClass }}">{{ $pos ?: '-' }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="p-4 text-gray-500">No data</td>
                                </tr>
                            @endforelse

                            <tr class="bg-gray-50 border-t">
                                <td colspan="2" class="p-3 font-semibold">TOTAL KEWAJIBAN</td>
                                <td class="p-3 text-right font-semibold">{{ number_format($liabilitiesTotal, 2, ',', '.') }}
                                </td>
                                <td class="p-3"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white border rounded">
                <div class="p-3 border-b font-semibold text-gray-900">EKUITAS</div>
                <div class="bs-wrap">
                    <table class="bs-table w-full text-sm">
                        <thead>
                            <tr class="text-left border-b">
                                <th class="p-3 w-28">Code</th>
                                <th class="p-3">Name</th>
                                <th class="p-3 text-right w-44">Amount</th>
                                <th class="p-3 w-20">Pos</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse ($equityItems as $it)
                                @php
                                    $amount = (float) data_get($it, 'amount', 0);
                                    $pos = strtoupper((string) data_get($it, 'pos', ''));
                                    $neg = $amount < 0;
                                    $posClass = $pos === 'D' ? 'bg-blue-50 text-blue-700 border-blue-200' : ($pos === 'K' ? 'bg-purple-50 text-purple-700 border-purple-200' : 'bg-gray-50 text-gray-700 border-gray-200');
                                @endphp
                                <tr>
                                    <td class="p-3 whitespace-nowrap">{{ data_get($it, 'code') }}</td>
                                    <td class="p-3">{{ data_get($it, 'name') }}</td>
                                    <td class="p-3 text-right {{ $neg ? 'text-red-600' : '' }}">
                                        {{ number_format($amount, 2, ',', '.') }}
                                    </td>
                                    <td class="p-3">
                                        <span class="pos-chip border {{ $posClass }}">{{ $pos ?: '-' }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="p-4 text-gray-500">No data</td>
                                </tr>
                            @endforelse

                            @php
                                $cpPosClass = $cpPos === 'D' ? 'bg-blue-50 text-blue-700 border-blue-200' : ($cpPos === 'K' ? 'bg-purple-50 text-purple-700 border-purple-200' : 'bg-gray-50 text-gray-700 border-gray-200');
                                $cpNeg = $cpAmount < 0;
                            @endphp
                            <tr class="bg-gray-50">
                                <td class="p-3 whitespace-nowrap">-</td>
                                <td class="p-3 font-medium">
                                    LABA BERJALAN <span class="text-xs text-gray-500">({{ $profitBasisLabel }})</span>
                                </td>
                                <td class="p-3 text-right font-medium {{ $cpNeg ? 'text-red-600' : '' }}">
                                    {{ number_format($cpAmount, 2, ',', '.') }}
                                </td>
                                <td class="p-3">
                                    <span class="pos-chip border {{ $cpPosClass }}">{{ $cpPos ?: '-' }}</span>
                                </td>
                            </tr>

                            <tr class="bg-gray-50 border-t">
                                <td colspan="2" class="p-3 font-semibold">TOTAL EKUITAS</td>
                                <td class="p-3 text-right font-semibold">{{ number_format($equityTotal, 2, ',', '.') }}
                                </td>
                                <td class="p-3"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white border rounded p-4">
                <div class="flex justify-between gap-3 text-sm">
                    <div class="text-gray-600">TOTAL KEWAJIBAN + EKUITAS</div>
                    <div class="font-semibold">{{ number_format($rhsTotal, 2, ',', '.') }}</div>
                </div>
                <div class="mt-2 flex justify-between gap-3 text-sm">
                    <div class="text-gray-600">SELISIH</div>
                    <div class="font-semibold {{ $diffClass }}">{{ number_format((float) $difference, 2, ',', '.') }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection

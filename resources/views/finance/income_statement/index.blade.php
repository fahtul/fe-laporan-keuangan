@extends('finance.report-layout')

@php
    $taxRateVal = $taxRate ?? null;
@endphp

@section('title', 'Laba Rugi')
@section('subtitle', 'Income Statement')

@section('header_actions')
    <a class="px-4 py-2 rounded border" href="{{ route('finance.income_statement.index', ['year' => $year]) }}">
        Reset</a>
@endsection

@section('header_meta')
    <span class="report-chip">Periode: <span class="font-semibold">{{ data_get($period, 'from_date', $fromDate) }} -
            {{ data_get($period, 'to_date', $toDate) }}</span></span>
    <span class="report-chip">Include zero: <span class="font-semibold">{{ $includeZero ? 'Ya' : 'Tidak' }}</span></span>
    <span class="report-chip">Include header: <span class="font-semibold">{{ $includeHeader ? 'Ya' : 'Tidak' }}</span></span>
    @if (!is_null($taxRateVal))
        <span class="report-chip">Tax rate: <span
                class="font-semibold">{{ number_format(((float) $taxRateVal) * 100, 2, ',', '.') }}%</span></span>
    @endif
@endsection

@section('tools')
    <style>
        .is-wrap {
            max-height: 70vh;
            overflow: auto;
        }

        .is-table thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #f9fafb;
        }

        .badge {
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

    <form method="GET" action="{{ route('finance.income_statement.index') }}" class="bg-white border rounded p-4"
        id="incomeStatementFilterForm">
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

        <div class="mt-3 grid md:grid-cols-4 gap-3">
            <div class="md:col-span-2 flex items-end gap-3">
                <input type="hidden" name="apply_tax" value="0">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="apply_tax" value="1" class="rounded border-gray-300"
                        {{ $applyTax ? 'checked' : '' }}>
                    <span class="text-sm">Hitung pajak</span>
                </label>

                <div class="flex-1">
                    <label class="block text-sm mb-1">Tax rate</label>
                    <input type="number" name="tax_rate" step="0.01" min="0" max="1"
                        value="{{ $applyTax ? (string) ($taxRate ?? 0.11) : '' }}" placeholder="Contoh: 0.11"
                        class="border rounded px-3 py-2 w-full">
                </div>
            </div>

            <div class="md:col-span-2 flex items-end gap-2 flex-wrap">
                <button type="submit" class="px-4 py-2 rounded bg-indigo-600 text-white">Tampilkan</button>
                <button type="submit" name="preset" value="year" class="px-4 py-2 rounded border">Tahun ini</button>
                <button type="submit" name="preset" value="month" class="px-4 py-2 rounded border">Bulan ini</button>
                <a class="px-4 py-2 rounded border"
                    href="{{ route('finance.income_statement.index', ['year' => $year]) }}">Reset</a>
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

    <div class="bg-white border rounded">
        <div class="is-wrap">
            <table class="is-table min-w-full text-sm">
                <thead>
                    <tr class="text-left border-b">
                        <th class="p-3 w-32">Code</th>
                        <th class="p-3">Name</th>
                        <th class="p-3 text-right w-44">Amount</th>
                    </tr>
                </thead>

                <tbody class="divide-y">
                    @php
                        $hasAny = false;
                    @endphp

                    @foreach ($sections as $section)
                        @php
                            $title = (string) data_get($section, 'title', '');
                            $items = data_get($section, 'items', []) ?: [];
                            $total = (float) data_get($section, 'total', 0);
                            $hasAny = $hasAny || count($items) > 0;
                        @endphp

                        <tr class="bg-gray-50">
                            <td class="p-3 font-semibold" colspan="3">{{ $title }}</td>
                        </tr>

                        @forelse ($items as $it)
                            @php
                                $amount = (float) data_get($it, 'amount', 0);
                                $neg = $amount < 0;
                            @endphp
                            <tr>
                                <td class="p-3 whitespace-nowrap">{{ data_get($it, 'code') }}</td>
                                <td class="p-3">{{ data_get($it, 'name') }}</td>
                                <td class="p-3 text-right {{ $neg ? 'text-red-600' : '' }}">
                                    {{ number_format($amount, 2, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="p-3 text-gray-500">No data</td>
                            </tr>
                        @endforelse

                        <tr class="bg-gray-50 border-t">
                            <td colspan="2" class="p-3 font-semibold">
                                JUMLAH {{ $title }}
                            </td>
                            <td class="p-3 text-right font-semibold">
                                {{ number_format($total, 2, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach

                    @if (empty($sections) || !$hasAny)
                        <tr>
                            <td colspan="3" class="p-4 text-gray-500">No data</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    @php
        $net = (float) data_get($summary, 'net_profit_after_tax', 0);
        $netPos = (string) data_get($summary, 'net_profit_pos', '');
        $isLoss = $net < 0 || strtolower($netPos) === 'loss';
        $badgeClass = $isLoss ? 'bg-red-50 text-red-700 border-red-200' : 'bg-green-50 text-green-700 border-green-200';
    @endphp

    <div class="bg-white border rounded p-4">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="font-semibold text-gray-900">Ringkasan</div>
                <div class="text-sm text-gray-500">Rekap hasil per periode.</div>
            </div>
            <span class="badge {{ $badgeClass }}">
                {{ $isLoss ? 'LOSS' : 'PROFIT' }}
            </span>
        </div>

        <div class="mt-4 grid md:grid-cols-2 gap-3 text-sm">
            <div class="flex justify-between gap-3 border rounded p-3">
                <span class="text-gray-600">JUMLAH PENDAPATAN</span>
                <span
                    class="font-semibold">{{ number_format((float) data_get($summary, 'total_revenue', 0), 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between gap-3 border rounded p-3">
                <span class="text-gray-600">JUMLAH HPP</span>
                <span
                    class="font-semibold">{{ number_format((float) data_get($summary, 'total_cogs', 0), 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between gap-3 border rounded p-3">
                <span class="text-gray-600">LABA KOTOR</span>
                <span
                    class="font-semibold">{{ number_format((float) data_get($summary, 'gross_profit', 0), 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between gap-3 border rounded p-3">
                <span class="text-gray-600">JUMLAH BIAYA OPERASIONAL</span>
                <span
                    class="font-semibold">{{ number_format((float) data_get($summary, 'total_operating_expense', 0), 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between gap-3 border rounded p-3">
                <span class="text-gray-600">LABA BERSIH (OPERASIONAL)</span>
                <span
                    class="font-semibold">{{ number_format((float) data_get($summary, 'operating_profit', 0), 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between gap-3 border rounded p-3">
                <span class="text-gray-600">PAJAK @if (!is_null($taxRateVal))
                        ({{ number_format(((float) $taxRateVal) * 100, 2, ',', '.') }}%)
                    @endif
                </span>
                <span class="font-semibold {{ ((float) data_get($summary, 'tax_amount', 0)) < 0 ? 'text-red-600' : '' }}">
                    {{ number_format((float) data_get($summary, 'tax_amount', 0), 2, ',', '.') }}
                </span>
            </div>
            <div class="flex justify-between gap-3 border rounded p-3 md:col-span-2">
                <span class="text-gray-600">LABA BERSIH SETELAH PAJAK</span>
                <span class="font-semibold {{ $isLoss ? 'text-red-600' : 'text-green-700' }}">
                    {{ number_format($net, 2, ',', '.') }}
                </span>
            </div>
        </div>
    </div>
@endsection

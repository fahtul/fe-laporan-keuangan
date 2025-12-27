@extends('finance.report-layout')

@php
    $periodFrom = (string) data_get($period, 'from_date', $fromDate);
    $periodTo = (string) data_get($period, 'to_date', $toDate);

    $rec = $reconciliation ?? [];
    $reconciled = (bool) data_get($rec, 'reconciled', false);
    $recDiff = (float) data_get($rec, 'difference', 0);
    $recDiffOk = abs($recDiff) < 0.01;

    $badgeClass = ($reconciled || $recDiffOk)
        ? 'bg-green-50 text-green-700 border-green-200'
        : 'bg-red-50 text-red-700 border-red-200';

    $cashBegin = (float) data_get($cash, 'begin', data_get($cash, 'beginning', 0));
    $cashChange = (float) data_get($cash, 'change', data_get($cash, 'net_change', 0));
    $cashEndActual = (float) data_get($cash, 'end', data_get($cash, 'ending', 0));
    $cashEndCalc = (float) data_get($rec, 'end_calc', ($cashBegin + $cashChange));
@endphp

@section('title', 'Arus Kas')
@section('subtitle', 'Cash Flow (CFO/CFI/CFF)')

@section('header_actions')
    <a class="px-4 py-2 rounded border bg-white" href="{{ route('finance.cash_flow.index', ['year' => $year]) }}">Reset</a>
    @if (in_array(auth()->user()->role ?? 'viewer', ['admin', 'accountant'], true))
        <a class="px-4 py-2 rounded border bg-white" href="{{ route('finance.accounts.cashflow_mapping.index') }}">
            Mapping COA
        </a>
    @endif
@endsection

@section('header_meta')
    <span class="report-chip">Periode: <span class="font-semibold">{{ $periodFrom }} - {{ $periodTo }}</span></span>
    <span class="report-chip">Cash prefix: <span class="font-semibold">{{ $cashPrefix }}</span></span>
    <span class="report-chip">Include zero: <span class="font-semibold">{{ $includeZero ? 'Ya' : 'Tidak' }}</span></span>
    <span class="report-chip">Include details: <span class="font-semibold">{{ $includeDetails ? 'Ya' : 'Tidak' }}</span></span>
    <span class="report-chip border {{ $badgeClass }}">
        {{ ($reconciled || $recDiffOk) ? 'RECONCILED' : 'NOT RECONCILED' }}
        <span class="font-semibold">({{ number_format($recDiff, 2, ',', '.') }})</span>
    </span>
@endsection

@section('tools')
    <style>
        .cf-wrap {
            max-height: 70vh;
            overflow: auto;
        }

        .cf-table thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #f9fafb;
        }
    </style>

    <form method="GET" action="{{ route('finance.cash_flow.index') }}" class="bg-white border rounded p-4">
        <input type="hidden" name="year" value="{{ $year }}">

        <div class="grid md:grid-cols-4 gap-3">
            <div>
                <label class="block text-sm mb-1">Dari tanggal</label>
                <input type="date" name="from_date" value="{{ $fromDate }}" class="border rounded px-3 py-2 w-full"
                    required>
            </div>

            <div>
                <label class="block text-sm mb-1">Sampai tanggal</label>
                <input type="date" name="to_date" value="{{ $toDate }}" class="border rounded px-3 py-2 w-full" required>
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
                <input type="hidden" name="include_details" value="0">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="include_details" value="1" class="rounded border-gray-300"
                        {{ $includeDetails ? 'checked' : '' }}>
                    <span class="text-sm">Include details</span>
                </label>
            </div>
        </div>

        <div class="mt-3 grid md:grid-cols-4 gap-3">
            <div class="md:col-span-2">
                <label class="block text-sm mb-1">Cash prefix</label>
                <input type="text" name="cash_prefix" value="{{ $cashPrefix }}" class="border rounded px-3 py-2 w-full"
                    placeholder="Contoh: 11">
                <p class="text-xs text-gray-500 mt-1">Prefix akun kas/bank (COA), default: 11.</p>
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm mb-1">Cash account IDs (optional)</label>
                <input type="text" name="cash_account_ids"
                    value="{{ !empty($cashAccountIds ?? []) ? implode(',', $cashAccountIds) : '' }}"
                    class="border rounded px-3 py-2 w-full" placeholder="id1,id2,id3">
            </div>
        </div>

        <div class="mt-3 flex items-end gap-2 flex-wrap">
            <button type="submit" class="px-4 py-2 rounded bg-indigo-600 text-white">Tampilkan</button>
            <button type="submit" name="preset" value="year" class="px-4 py-2 rounded border bg-gray-50 border-gray-300">
                Tahun ini
            </button>
            <button type="submit" name="preset" value="month" class="px-4 py-2 rounded border bg-gray-50 border-gray-300">
                Bulan ini
            </button>
            <a class="px-4 py-2 rounded border bg-white" href="{{ route('finance.cash_flow.index', ['year' => $year]) }}">Reset</a>
        </div>
    </form>
@endsection

@section('content')
    @if (!empty($apiError))
        <div class="p-3 rounded bg-yellow-100 text-yellow-800">
            {{ $apiError }}
        </div>
    @endif

    <div class="bg-white border rounded p-4">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="font-semibold text-gray-900">Ringkasan Cash</div>
                <div class="text-sm text-gray-500">Beginning + net change = ending (calc), dibandingkan ending aktual.</div>
            </div>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {{ $badgeClass }}">
                {{ ($reconciled || $recDiffOk) ? 'RECONCILED' : 'NOT RECONCILED' }}
            </span>
        </div>

        <div class="mt-4 grid md:grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
            <div class="flex justify-between gap-3 border rounded p-3">
                <span class="text-gray-600">BEGINNING CASH</span>
                <span class="font-semibold">{{ number_format($cashBegin, 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between gap-3 border rounded p-3">
                <span class="text-gray-600">NET CHANGE</span>
                <span class="font-semibold {{ $cashChange < 0 ? 'text-red-600' : '' }}">
                    {{ number_format($cashChange, 2, ',', '.') }}
                </span>
            </div>
            <div class="flex justify-between gap-3 border rounded p-3">
                <span class="text-gray-600">ENDING CASH (ACTUAL)</span>
                <span class="font-semibold">{{ number_format($cashEndActual, 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between gap-3 border rounded p-3">
                <span class="text-gray-600">ENDING CASH (CALC)</span>
                <span class="font-semibold">{{ number_format($cashEndCalc, 2, ',', '.') }}</span>
            </div>
        </div>

        <div class="mt-3 flex justify-between gap-3 text-sm">
            <span class="text-gray-600">SELISIH</span>
            <span class="font-semibold {{ $recDiffOk ? 'text-green-700' : 'text-red-700' }}">
                {{ number_format($recDiff, 2, ',', '.') }}
            </span>
        </div>
    </div>

    @php
        $activityKeys = [
            'operating' => ['label' => 'OPERATING (CFO)', 'chip' => 'bg-indigo-50 text-indigo-700 border-indigo-200'],
            'investing' => ['label' => 'INVESTING (CFI)', 'chip' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
            'financing' => ['label' => 'FINANCING (CFF)', 'chip' => 'bg-amber-50 text-amber-700 border-amber-200'],
        ];
    @endphp

    <div class="grid lg:grid-cols-3 gap-4">
        @foreach ($activityKeys as $k => $meta)
            @php
                $block = data_get($activities, $k, []) ?: [];
                $items = data_get($block, 'items', []) ?: [];
                $total = (float) data_get($block, 'total', data_get($totals, $k, 0));
            @endphp

            <div class="bg-white border rounded">
                <div class="p-3 border-b flex items-center justify-between gap-3">
                    <div class="font-semibold text-gray-900">{{ $meta['label'] }}</div>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {{ $meta['chip'] }}">
                        {{ number_format($total, 2, ',', '.') }}
                    </span>
                </div>

                <div class="cf-wrap">
                    <table class="cf-table w-full text-sm">
                        <thead>
                            <tr class="text-left border-b">
                                <th class="p-3 w-24">Code</th>
                                <th class="p-3">Name</th>
                                <th class="p-3 text-right w-40">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
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
                                    <td colspan="3" class="p-4 text-gray-500">No data</td>
                                </tr>
                            @endforelse

                            <tr class="bg-gray-50 border-t">
                                <td colspan="2" class="p-3 font-semibold">TOTAL</td>
                                <td class="p-3 text-right font-semibold">
                                    {{ number_format($total, 2, ',', '.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>

    <div class="bg-white border rounded p-4">
        <div class="font-semibold text-gray-900">Reconciliation</div>
        <div class="mt-3 grid md:grid-cols-2 gap-3 text-sm">
            <div class="flex justify-between gap-3 border rounded p-3">
                <span class="text-gray-600">BEGIN + NET CHANGE</span>
                <span class="font-semibold">{{ number_format($cashBegin + $cashChange, 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between gap-3 border rounded p-3">
                <span class="text-gray-600">ENDING (CALC)</span>
                <span class="font-semibold">{{ number_format($cashEndCalc, 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between gap-3 border rounded p-3">
                <span class="text-gray-600">ENDING (ACTUAL)</span>
                <span class="font-semibold">{{ number_format($cashEndActual, 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between gap-3 border rounded p-3">
                <span class="text-gray-600">DIFFERENCE</span>
                <span class="font-semibold {{ $recDiffOk ? 'text-green-700' : 'text-red-700' }}">
                    {{ number_format($recDiff, 2, ',', '.') }}
                </span>
            </div>
        </div>
    </div>
@endsection


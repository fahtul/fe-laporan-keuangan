@extends('finance.report-layout')

@section('title', 'Neraca Saldo')
@section('subtitle', 'Trial Balance')

@section('header_actions')
    <a class="px-4 py-2 rounded border" href="{{ route('finance.trial_balance.index', ['year' => $year]) }}">Reset</a>
@endsection

@section('header_meta')
    <span class="report-chip">
        Periode:
        <span class="font-semibold">{{ data_get($period, 'from_date', $fromDate) }} -
            {{ data_get($period, 'to_date', $toDate) }}</span>
    </span>
    <span class="report-chip">Include zero: <span class="font-semibold">{{ $includeZero ? 'Ya' : 'Tidak' }}</span></span>
    <span class="report-chip">Include header: <span class="font-semibold">{{ $includeHeader ? 'Ya' : 'Tidak' }}</span></span>
@endsection

@section('tools')
    <style>
        .table-wrap {
            max-height: 70vh;
            overflow: auto;
        }

        .tb thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #f9fafb;
        }

        .tb tbody tr:hover {
            background: #fafafa;
        }
    </style>

    <form method="GET" action="{{ route('finance.trial_balance.index') }}" class="bg-white border rounded p-4">
        <div class="grid md:grid-cols-5 gap-3">
            <div>
                <label class="block text-sm mb-1">Tahun</label>
                <select name="year" class="border rounded px-3 py-2 w-full">
                    @php
                        $yNow = (int) now()->format('Y');
                        $years = range($yNow - 5, $yNow + 1);
                    @endphp
                    @foreach ($years as $y)
                        <option value="{{ $y }}" {{ (string) $year === (string) $y ? 'selected' : '' }}>
                            {{ $y }}
                        </option>
                    @endforeach
                </select>
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

            <div class="flex items-end">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="include_zero" value="1" class="rounded border-gray-300"
                        {{ $includeZero ? 'checked' : '' }}>
                    <span class="text-sm">Include zero</span>
                </label>
            </div>

            <div class="flex items-end">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="include_header" value="1" class="rounded border-gray-300"
                        {{ $includeHeader ? 'checked' : '' }}>
                    <span class="text-sm">Include header</span>
                </label>
            </div>
        </div>

        <div class="mt-4 flex items-center gap-2 flex-wrap">
            <button type="submit" class="px-4 py-2 rounded bg-green-600 text-white shadow-sm hover:bg-indigo-700">
                Tampilkan
            </button>

            <button type="submit" name="preset" value="year"
                class="px-4 py-2 rounded border border-gray-300 bg-gray-50 text-gray-900 hover:bg-gray-100">
                Tahun (sesuai dropdown)
            </button>

            <button type="submit" name="preset" value="month"
                class="px-4 py-2 rounded border border-gray-300 bg-gray-50 text-gray-900 hover:bg-gray-100">
                Bulan ini (sesuai tahun)
            </button>

            <a class="px-4 py-2 rounded border border-gray-300 bg-white text-gray-900 hover:bg-gray-50"
                href="{{ route('finance.trial_balance.index', ['year' => $year]) }}">
                Reset
            </a>
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
        <div class="table-wrap">
            <table class="tb min-w-full text-sm">
                <thead>
                    <tr class="text-left border-b">
                        <th class="p-3">Code</th>
                        <th class="p-3">Name</th>
                        <th class="p-3">Type</th>
                        <th class="p-3 text-right">Awal (D)</th>
                        <th class="p-3 text-right">Awal (K)</th>
                        <th class="p-3 text-right">Mutasi (D)</th>
                        <th class="p-3 text-right">Mutasi (K)</th>
                        <th class="p-3 text-right">Akhir (D)</th>
                        <th class="p-3 text-right">Akhir (K)</th>
                    </tr>
                </thead>

                <tbody class="divide-y">
                    @forelse ($items as $it)
                        @php
                            $openingDebit = (float) data_get($it, 'opening.debit', 0);
                            $openingCredit = (float) data_get($it, 'opening.credit', 0);
                            $mutationDebit = (float) data_get($it, 'mutation.debit', 0);
                            $mutationCredit = (float) data_get($it, 'mutation.credit', 0);
                            $closingDebit = (float) data_get($it, 'closing.debit', 0);
                            $closingCredit = (float) data_get($it, 'closing.credit', 0);
                        @endphp

                        <tr>
                            <td class="p-3 whitespace-nowrap">{{ data_get($it, 'code') }}</td>
                            <td class="p-3">{{ data_get($it, 'name') }}</td>
                            <td class="p-3 whitespace-nowrap">{{ data_get($it, 'type') }}</td>

                            <td class="p-3 text-right">{{ number_format($openingDebit, 2, ',', '.') }}</td>
                            <td class="p-3 text-right">{{ number_format($openingCredit, 2, ',', '.') }}</td>
                            <td class="p-3 text-right">{{ number_format($mutationDebit, 2, ',', '.') }}</td>
                            <td class="p-3 text-right">{{ number_format($mutationCredit, 2, ',', '.') }}</td>
                            <td class="p-3 text-right">{{ number_format($closingDebit, 2, ',', '.') }}</td>
                            <td class="p-3 text-right">{{ number_format($closingCredit, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="p-4 text-gray-500">No data</td>
                        </tr>
                    @endforelse
                </tbody>

                @if (!empty($items))
                    @php
                        $tOpeningDebit = (float) data_get($totals, 'opening_debit', 0);
                        $tOpeningCredit = (float) data_get($totals, 'opening_credit', 0);
                        $tMutationDebit = (float) data_get($totals, 'mutation_debit', 0);
                        $tMutationCredit = (float) data_get($totals, 'mutation_credit', 0);
                        $tClosingDebit = (float) data_get($totals, 'closing_debit', 0);
                        $tClosingCredit = (float) data_get($totals, 'closing_credit', 0);
                    @endphp

                    <tfoot class="bg-gray-50 border-t">
                        <tr>
                            <td colspan="3" class="p-3 font-semibold text-right">TOTAL</td>
                            <td class="p-3 text-right font-semibold">{{ number_format($tOpeningDebit, 2, ',', '.') }}</td>
                            <td class="p-3 text-right font-semibold">{{ number_format($tOpeningCredit, 2, ',', '.') }}</td>
                            <td class="p-3 text-right font-semibold">{{ number_format($tMutationDebit, 2, ',', '.') }}</td>
                            <td class="p-3 text-right font-semibold">{{ number_format($tMutationCredit, 2, ',', '.') }}
                            </td>
                            <td class="p-3 text-right font-semibold">{{ number_format($tClosingDebit, 2, ',', '.') }}</td>
                            <td class="p-3 text-right font-semibold">{{ number_format($tClosingCredit, 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@endsection

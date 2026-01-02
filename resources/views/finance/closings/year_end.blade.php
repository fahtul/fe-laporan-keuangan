@extends('finance.report-layout')

@section('title', 'Tutup Buku')
@section('subtitle', 'Year-End Closing')

@section('header_actions')
    <a class="px-4 py-2 rounded border bg-white text-gray-900 hover:bg-gray-50"
        href="{{ route('finance.closings.year_end.index', ['year' => $year]) }}">Reset</a>
@endsection

@section('header_meta')
    @php
        $userRole = auth()->user()?->role ?? 'viewer';
        $canRun = in_array($userRole, ['admin', 'accountant'], true);

        $statusLabel = $isClosed ? 'Closed' : 'Open';
        $statusClass = $isClosed
            ? 'bg-green-50 text-green-700 border-green-200'
            : 'bg-yellow-50 text-yellow-800 border-yellow-200';
    @endphp

    <span class="report-chip">Year: <span class="font-semibold">{{ $year }}</span></span>
    <span class="report-chip {{ $statusClass }}">Status: <span class="font-semibold">{{ $statusLabel }}</span></span>
    @if (!$canRun)
        <span class="report-chip">Mode: <span class="font-semibold">Read-only</span></span>
    @endif
@endsection

@section('tools')
    <style>
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

    @if (!empty($accountsError))
        <div class="p-3 rounded bg-yellow-100 text-yellow-800 mb-3">
            {{ $accountsError }}
        </div>
    @endif

    @if (!empty($apiError))
        <div class="p-3 rounded bg-yellow-100 text-yellow-800 mb-3">
            {{ $apiError }}
        </div>
    @endif

    @php
        $closingResult = session('closing_result');
        $closingResult = is_array($closingResult) ? $closingResult : [];
        $closingResClosingId = data_get($closingResult, 'closing_entry_id');
        $closingResOpeningId = data_get($closingResult, 'opening_entry_id');
        $closingResNetProfit = data_get($closingResult, 'net_profit');

        $closingId = $closingEntryId ?? $closingResClosingId;
        $openingId = $openingEntryId ?? $closingResOpeningId;
    @endphp

    @if (!empty($closingId))
        <div class="bg-white border rounded p-4">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="font-semibold text-gray-900">Closing sudah tersedia</div>
                    <div class="text-sm text-gray-600 mt-1">
                        Jurnal closing: <span class="font-mono">{{ $closingId }}</span>
                        @if (!empty($openingId))
                            · Opening: <span class="font-mono">{{ $openingId }}</span>
                        @endif
                    </div>
                </div>
                <div class="flex gap-2 flex-wrap justify-end">
                    <a class="px-4 py-2 rounded border bg-white text-gray-900 hover:bg-gray-50"
                        href="{{ route('finance.journal_entries.edit', $closingId) }}">Buka Jurnal Closing</a>
                    @if (!empty($openingId))
                        <a class="px-4 py-2 rounded border bg-white text-gray-900 hover:bg-gray-50"
                            href="{{ route('finance.journal_entries.edit', $openingId) }}">Buka Opening</a>
                    @endif
                </div>
            </div>

            @if (!is_null($closingResNetProfit))
                <div class="mt-3 text-sm">
                    Net profit: <span
                        class="font-semibold">{{ number_format((float) $closingResNetProfit, 2, ',', '.') }}</span>
                </div>
            @endif
        </div>
    @endif

    <form method="GET" action="{{ route('finance.closings.year_end.index') }}" class="bg-white border rounded p-4">
        <div class="grid md:grid-cols-4 gap-3">
            <div>
                <label class="block text-sm mb-1">Tahun</label>
                <input type="number" name="year" min="2000" max="2100" value="{{ $year }}"
                    class="border rounded px-3 py-2 w-full">
            </div>
            <div class="md:col-span-3 flex items-end gap-2">
                <button type="submit" class="px-4 py-2 rounded border bg-white text-gray-900 hover:bg-gray-50">Cek
                    Status</button>
            </div>
        </div>
    </form>

    <form method="POST" action="{{ route('finance.closings.year_end.store') }}" class="bg-white border rounded p-4">
        @csrf

        <input type="hidden" name="year" value="{{ $year }}">

        <div class="grid md:grid-cols-4 gap-3">
            <div>
                <label class="block text-sm mb-1">Tanggal closing</label>
                <input type="date" name="date" value="{{ old('date', $year . '-12-31') }}"
                    class="border rounded px-3 py-2 w-full">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm mb-1">Akun Retained Earnings</label>
                <select name="retained_earnings_account_id" class="border rounded px-3 py-2 w-full"
                    {{ $canRun ? '' : 'disabled' }} required>
                    <option value="">-- pilih akun equity --</option>
                    @php
                        $defaultReId = data_get($closing, 'retained_earnings_account.id');
                        $selectedReId = old('retained_earnings_account_id', $defaultReId);
                    @endphp

                    @foreach ($equityAccounts as $a)
                        <option value="{{ $a['id'] }}" {{ $selectedReId === $a['id'] ? 'selected' : '' }}>
                            {{ $a['code'] }} - {{ $a['name'] }}
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">Akun ini dipakai untuk memindahkan laba/rugi tahun berjalan.</p>
            </div>

            <div class="flex items-end">
                <input type="hidden" name="generate_opening" value="0">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="generate_opening" value="1" class="rounded border-gray-300"
                        {{ old('generate_opening', '1') === '1' ? 'checked' : '' }} {{ $canRun ? '' : 'disabled' }}>
                    <span class="text-sm">Generate opening tahun berikutnya</span>
                </label>
            </div>
        </div>

        <div class="mt-3">
            <label class="block text-sm mb-1">Memo (opsional)</label>
            <textarea name="memo" rows="3" class="border rounded px-3 py-2 w-full" {{ $canRun ? '' : 'disabled' }}
                placeholder="Contoh: Closing tahun {{ $year }}...">{{ old('memo') }}</textarea>
        </div>

        @if ($canRun)
            <div class="mt-4 flex items-center gap-2 flex-wrap">
                <button type="submit" class="px-4 py-2 rounded bg-indigo-600 text-white">
                    Run Closing (POSTED)
                </button>
                @if ($isClosed)
                    <span class="text-sm text-gray-600">Status sudah closed; jalankan lagi hanya jika memang
                        diperlukan.</span>
                @endif
            </div>
        @else
            <div class="mt-4 text-sm text-gray-600">
                Kamu hanya bisa melihat status. Minta admin/accountant untuk menjalankan closing.
            </div>
        @endif
    </form>
@endsection

@section('content')
    @php
        $preview = data_get($closing, 'preview_entry');
        $lines = data_get($preview, 'lines', []);
        $totD = (float) data_get($preview, 'totals.debit', 0);
        $totK = (float) data_get($preview, 'totals.credit', 0);
        $balanced = abs($totD - $totK) < 0.01;

        $niAmount = (float) data_get($closing, 'net_income.amount', 0);
        $niSide = strtoupper((string) data_get($closing, 'net_income.side', ''));
        $reCode = data_get($closing, 'retained_earnings_account.code');
        $reName = data_get($closing, 'retained_earnings_account.name');
        $closingDate = data_get($preview, 'date', data_get($closing, 'closing_date_default', $year . '-12-31'));
    @endphp

    <div class="bg-white border rounded p-4">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="text-lg font-semibold text-gray-900">Preview Year-End Closing</div>
                <div class="text-sm text-gray-600 mt-1">
                    Tanggal: <span class="font-mono">{{ $closingDate }}</span>
                    · Retained Earnings: <span class="font-mono">{{ $reCode }}</span> — {{ $reName }}
                </div>
            </div>
            <span
                class="badge {{ $balanced ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200' }}">
                {{ $balanced ? 'BALANCE' : 'NOT BALANCE' }}
            </span>
        </div>

        <div class="grid md:grid-cols-3 gap-3 mt-4">
            <div class="border rounded p-3">
                <div class="text-xs text-gray-500">Net Income</div>
                <div class="text-lg font-semibold">
                    {{ number_format($niAmount, 2, ',', '.') }} <span
                        class="text-sm text-gray-500">{{ $niSide }}</span>
                </div>
            </div>
            <div class="border rounded p-3">
                <div class="text-xs text-gray-500">Total Debit (Preview)</div>
                <div class="text-lg font-semibold">{{ number_format($totD, 2, ',', '.') }}</div>
            </div>
            <div class="border rounded p-3">
                <div class="text-xs text-gray-500">Total Credit (Preview)</div>
                <div class="text-lg font-semibold">{{ number_format($totK, 2, ',', '.') }}</div>
            </div>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left">
                        <th class="px-3 py-2">Code</th>
                        <th class="px-3 py-2">Name</th>
                        <th class="px-3 py-2 text-right">Debit</th>
                        <th class="px-3 py-2 text-right">Credit</th>
                        <th class="px-3 py-2">Memo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lines as $ln)
                        <tr class="border-t">
                            <td class="px-3 py-2 font-mono">{{ data_get($ln, 'code') }}</td>
                            <td class="px-3 py-2">{{ data_get($ln, 'name') }}</td>
                            <td class="px-3 py-2 text-right">
                                {{ number_format((float) data_get($ln, 'debit', 0), 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right">
                                {{ number_format((float) data_get($ln, 'credit', 0), 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ data_get($ln, 'memo') }}</td>
                        </tr>
                    @empty
                        <tr class="border-t">
                            <td colspan="5" class="px-3 py-6 text-center text-gray-500">
                                Preview belum tersedia (lines kosong).
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

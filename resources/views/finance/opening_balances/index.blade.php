@extends('finance.layout')

@section('title', 'Neraca Awal / Saldo Awal')
@section('subtitle', 'Opening Balance (jurnal pembuka)')

@section('header_actions')
    @if (in_array(auth()->user()->role, ['admin', 'accountant']))
        @if (!$opening)
            <a href="{{ route('finance.opening_balances.create', ['year' => $year]) }}"
                class="px-4 py-2 rounded bg-black text-white">
                + Buat Opening Balance
            </a>
        @else
            @if (!empty($opening['id']))
                <form method="POST" action="{{ route('finance.opening_balances.destroy', ['id' => $opening['id'], 'year' => $year]) }}"
                    class="inline"
                    onsubmit="return confirm('Yakin hapus opening balance tahun {{ $year }}? Ini akan soft delete jurnal opening beserta lines.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-4 py-2 rounded bg-red-600 text-white hover:bg-red-700">
                        Delete
                    </button>
                </form>
            @endif
        @endif
    @endif
@endsection

@section('content')
    <div class="bg-white border rounded p-4">
        <form class="flex gap-2 mb-4 items-end" method="GET" action="{{ route('finance.opening_balances.index') }}">
            <div>
                <label class="block text-xs mb-1 text-gray-600">Tahun / opening_key</label>
                <input name="year" value="{{ $year }}" class="border rounded px-3 py-2 w-48" placeholder="2026" />
            </div>

            <button class="px-4 py-2 rounded bg-gray-900 text-white">Lihat</button>

            @if (in_array(auth()->user()->role, ['admin', 'accountant']) && !$opening)
                <a class="px-4 py-2 rounded border"
                    href="{{ route('finance.opening_balances.create', ['year' => $year]) }}">
                    Buat
                </a>
            @endif
        </form>

        @if ($apiError)
            <div class="p-3 rounded bg-red-100 text-red-800 mb-3">{{ $apiError }}</div>
        @endif
        @if ($errors->any())
            <div class="p-3 rounded bg-red-100 text-red-800 mb-3">
                {{ $errors->first('api') ?? $errors->first() }}
            </div>
        @endif

        @if (!$opening)
            <div class="p-3 rounded bg-gray-50 text-gray-700 border">
                Belum ada Opening Balance untuk <span class="font-semibold">{{ $year }}</span>.
            </div>
        @else
            @php
                $lines = $opening['lines'] ?? [];
                $totalDebit = collect($lines)->sum(fn($l) => (float) ($l['debit'] ?? 0));
                $totalCredit = collect($lines)->sum(fn($l) => (float) ($l['credit'] ?? 0));
                $diff = $totalDebit - $totalCredit;
            @endphp

            <div class="flex flex-wrap gap-3 mb-4">
                <div class="border rounded p-3 bg-gray-50">
                    <div class="text-xs text-gray-500">Tanggal</div>
                    <div class="font-semibold">{{ $opening['date'] ?? '-' }}</div>
                </div>
                <div class="border rounded p-3 bg-gray-50">
                    <div class="text-xs text-gray-500">Status</div>
                    <div class="font-semibold">
                        <span class="px-2 py-1 rounded bg-green-100 text-green-800 text-xs">POSTED</span>
                    </div>
                </div>
                <div class="border rounded p-3">
                    <div class="text-xs text-gray-500">Total Debit</div>
                    <div class="font-semibold">{{ number_format($totalDebit, 2, ',', '.') }}</div>
                </div>
                <div class="border rounded p-3">
                    <div class="text-xs text-gray-500">Total Kredit</div>
                    <div class="font-semibold">{{ number_format($totalCredit, 2, ',', '.') }}</div>
                </div>
                <div class="border rounded p-3 {{ abs($diff) < 0.005 ? 'bg-green-50' : 'bg-red-50' }}">
                    <div class="text-xs text-gray-500">Selisih</div>
                    <div class="font-semibold">{{ number_format($diff, 2, ',', '.') }}</div>
                </div>
            </div>

            @if (!empty($opening['memo']))
                <div class="mb-4 p-3 rounded bg-gray-50 border">
                    <div class="text-xs text-gray-500 mb-1">Memo</div>
                    <div class="text-sm">{{ $opening['memo'] }}</div>
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left p-3">Account</th>
                            <th class="text-right p-3">Debit</th>
                            <th class="text-right p-3">Credit</th>
                            <th class="text-left p-3">Memo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($lines as $l)
                            <tr class="border-t">
                                <td class="p-3">
                                    @php
                                        $accId = $l['account_id'] ?? null;
                                        $acc = $accId ? ($accountsById[$accId] ?? null) : null;
                                        $accLabel = $acc ? trim(($acc['code'] ?? '') . ' — ' . ($acc['name'] ?? '')) : null;
                                    @endphp
                                    <div class="font-medium">{{ $accLabel ?: ($accId ?? '-') }}</div>
                                    @if ($accId)
                                        <div class="text-xs text-gray-500">{{ $accId }}</div>
                                    @endif
                                </td>
                                <td class="p-3 text-right">{{ number_format((float) ($l['debit'] ?? 0), 2, ',', '.') }}</td>
                                <td class="p-3 text-right">{{ number_format((float) ($l['credit'] ?? 0), 2, ',', '.') }}
                                </td>
                                <td class="p-3">{{ $l['memo'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="p-4 text-gray-500" colspan="4">No lines</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (!empty($opening['id']))
                <div class="mt-4">
                    <a class="underline" href="{{ route('finance.journal_entries.edit', $opening['id']) }}">
                        Buka sebagai Journal Entry
                    </a>
                </div>
            @endif
        @endif
    </div>
@endsection

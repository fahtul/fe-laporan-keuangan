@extends('finance.layout')

@section('title', 'Journal Entries')
@section('subtitle', 'Draft / Posted / Void')

@section('header_actions')
    @if (in_array(auth()->user()->role, ['admin', 'accountant']))
        <a href="{{ route('finance.journal_entries.create') }}" class="px-4 py-2 rounded bg-black text-white">+ New</a>
    @endif
@endsection

@section('content')
    <div class="bg-white border rounded p-4">
        <form class="flex flex-wrap gap-2 mb-4" method="GET" action="{{ route('finance.journal_entries.index') }}">
            <input name="q" value="{{ $q }}" placeholder="Search memo / id..."
                class="border rounded px-3 py-2 w-72" />

            <select name="status" class="border rounded px-3 py-2">
                <option value="">All Status</option>
                <option value="draft" {{ $status === 'draft' ? 'selected' : '' }}>draft</option>
                <option value="posted" {{ $status === 'posted' ? 'selected' : '' }}>posted</option>
                <option value="void" {{ $status === 'void' ? 'selected' : '' }}>void</option>
            </select>

            <input type="date" name="from_date" value="{{ $from_date }}" class="border rounded px-3 py-2" />
            <input type="date" name="to_date" value="{{ $to_date }}" class="border rounded px-3 py-2" />

            <button class="px-4 py-2 rounded bg-gray-900 text-white">Filter</button>
        </form>

        @if ($apiError)
            <div class="p-3 rounded bg-red-100 text-red-800 mb-3">{{ $apiError }}</div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left p-3">Date</th>
                        <th class="text-left p-3">Memo</th>
                        <th class="text-left p-3">Status</th>
                        <th class="text-right p-3">Debit</th>
                        <th class="text-right p-3">Credit</th>
                        <th class="text-right p-3">Lines</th>
                        <th class="text-right p-3">Balance</th>
                        <th class="text-right p-3">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($entries as $e)
                        @php
                            $st = $e['status'] ?? '';
                            $badge =
                                $st === 'posted'
                                    ? 'bg-green-100 text-green-800'
                                    : ($st === 'draft'
                                        ? 'bg-yellow-100 text-yellow-800'
                                        : 'bg-gray-100 text-gray-800');

                            $debit = (float) ($e['total_debit'] ?? 0);
                            $credit = (float) ($e['total_credit'] ?? 0);
                            $balanced = round($debit * 100) === round($credit * 100) && $debit > 0;

                            $balanceBadge = $balanced ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800';

                            // date is DATE-ONLY; avoid timezone shift
                            $dRaw = (string) ($e['date'] ?? '');
                            $dateLabel = $dRaw !== '' ? substr($dRaw, 0, 10) : '';

                            $entryTypeRaw = (string) ($e['entry_type'] ?? ($e['entryType'] ?? ''));
                            $entryType = in_array($entryTypeRaw, ['opening', 'closing'], true) ? $entryTypeRaw : 'manual';
                            $typeBadge =
                                $entryType === 'opening'
                                    ? 'bg-blue-100 text-blue-800'
                                    : ($entryType === 'closing'
                                        ? 'bg-purple-100 text-purple-800'
                                        : 'bg-gray-100 text-gray-800');

                            $memoRaw = (string) ($e['memo'] ?? '');
                            $memoLabel = trim($memoRaw) !== '' ? $memoRaw : '-';
                            if ($entryType === 'opening' && ($memoLabel === '-' || trim($memoLabel) === '')) {
                                $yr = $dateLabel !== '' ? substr($dateLabel, 0, 4) : '';
                                $memoLabel = $yr !== '' ? ('Opening Balance ' . $yr) : 'Opening Balance';
                            }

                            $canWrite = in_array(auth()->user()->role, ['admin', 'accountant']);
                            $isDraft = $st === 'draft';
                        @endphp

                        <tr class="border-t">
                            <td class="p-3">{{ $dateLabel }}</td>

                            <td class="p-3">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <div class="font-medium">{{ $memoLabel }}</div>
                                    <span class="inline-flex px-2 py-0.5 rounded text-[11px] {{ $typeBadge }}">
                                        {{ $entryType }}
                                    </span>
                                </div>
                                <div class="text-xs text-gray-500">{{ $e['id'] ?? '' }}</div>
                            </td>

                            <td class="p-3">
                                <span class="inline-flex px-2 py-1 rounded text-xs {{ $badge }}">
                                    {{ $st ?: '-' }}
                                </span>
                            </td>

                            <td class="p-3 text-right">{{ number_format($debit, 2) }}</td>
                            <td class="p-3 text-right">{{ number_format($credit, 2) }}</td>
                            <td class="p-3 text-right">{{ (int) ($e['lines_count'] ?? 0) }}</td>

                            <td class="p-3 text-right">
                                <span class="inline-flex px-2 py-1 rounded text-xs {{ $balanceBadge }}">
                                    {{ $balanced ? 'balanced' : 'not balanced' }}
                                </span>
                            </td>

                            <td class="p-3 text-right">
                                <a class="underline" href="{{ route('finance.journal_entries.edit', $e['id']) }}">View</a>

                                @if ($canWrite && $isDraft)
                                    <span class="text-gray-300 mx-2">|</span>
                                    <a class="underline"
                                        href="{{ route('finance.journal_entries.edit', $e['id']) }}">Edit</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="p-4 text-gray-500" colspan="8">No data</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="mt-4">
                {{ $entries->links() }}
            </div>
        </div>
    </div>
@endsection

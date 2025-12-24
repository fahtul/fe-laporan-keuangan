@extends('finance.layout')

@section('title', 'Accounts')
@section('subtitle', 'Chart of Accounts')

@section('header_actions')
    @if (in_array(auth()->user()->role, ['admin', 'accountant']))
        <a href="{{ route('finance.accounts.create') }}" class="px-4 py-2 rounded bg-black text-white">+ New</a>
    @endif
@endsection

@section('content')
    <div class="bg-white border rounded p-4">
        <form class="flex gap-2 mb-4" method="GET" action="{{ route('finance.accounts.index') }}">
            <input name="q" value="{{ $q }}" placeholder="Search..." class="border rounded px-3 py-2 w-72" />
            <button class="px-4 py-2 rounded bg-gray-900 text-white">Search</button>
        </form>

        @if ($apiError)
            <div class="p-3 rounded bg-red-100 text-red-800 mb-3">{{ $apiError }}</div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left p-3">Code</th>
                        <th class="text-left p-3">Name</th>
                        <th class="text-left p-3">Type</th>
                        <th class="text-left p-3">Parent</th>
                        <th class="text-left p-3">Postable</th>
                        <th class="text-right p-3">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($accounts as $a)
                        <tr class="border-t">
                            <td class="p-3">{{ $a['code'] ?? '' }}</td>
                            <td class="p-3">{{ $a['name'] ?? '' }}</td>
                            <td class="p-3">{{ $a['type'] ?? '' }}</td>

                            <td class="p-3">
                                @php $pid = $a['parent_id'] ?? null; @endphp
                                {{ $pid ? $parentMap[$pid] ?? $pid : '-' }}
                            </td>

                            <td class="p-3">
                                @php $postable = (bool)($a['is_postable'] ?? true); @endphp
                                @if ($postable)
                                    <span class="px-2 py-1 rounded bg-green-100 text-green-800 text-xs">Yes</span>
                                @else
                                    <span class="px-2 py-1 rounded bg-gray-100 text-gray-800 text-xs">No</span>
                                @endif
                            </td>

                            <td class="p-3 text-right">
                                @if (in_array(auth()->user()->role, ['admin', 'accountant']))
                                    <a class="underline mr-3" href="{{ route('finance.accounts.edit', $a['id']) }}">Edit</a>

                                    <form method="POST" action="{{ route('finance.accounts.destroy', $a['id']) }}"
                                        class="inline" onsubmit="return confirm('Delete account ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="underline text-red-600">Delete</button>
                                    </form>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="p-4 text-gray-500" colspan="6">No data</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="mt-4">
                {{ $accounts->links() }}
            </div>
        </div>
    </div>
@endsection

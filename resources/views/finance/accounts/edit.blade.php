@extends('finance.layout')
@section('title', 'Edit Account')

@section('content')
    @php
        $type = (string) old('type', data_get($account, 'type', 'asset'));
        $normalBalance = in_array($type, ['asset', 'expense'], true) ? 'debit' : 'credit';
        $hasTransactions = (bool) data_get($account, 'has_transactions', false);

        $postableOld = (string) old('is_postable', data_get($account, 'is_postable', true) ? '1' : '0');
        $activeOld = (string) old('is_active', data_get($account, 'is_active', true) ? '1' : '0');
        $requiresBpOld = (string) old('requires_bp', data_get($account, 'requires_bp', false) ? '1' : '0');
        $subledgerOld = (string) old('subledger', (string) (data_get($account, 'subledger') ?? ''));
    @endphp

    <div class="bg-white border rounded p-4 max-w-xl">
        @if (!empty($parentsError))
            <div class="p-3 rounded bg-yellow-100 text-yellow-800 mb-3">
                {{ $parentsError }}
            </div>
        @endif

        <form method="POST" action="{{ route('finance.accounts.update', data_get($account, 'id')) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm mb-1">Code</label>
                <input name="code" readonly class="border rounded w-full px-3 py-2 bg-gray-100 cursor-not-allowed"
                    value="{{ old('code', data_get($account, 'code', '')) }}">
            </div>

            <div>
                <label class="block text-sm mb-1">Name</label>
                <input name="name" value="{{ old('name', data_get($account, 'name', '')) }}"
                    class="border rounded w-full px-3 py-2" required>
            </div>

            <div>
                <label class="block text-sm mb-1">Cash Flow Category (optional)</label>
                @php $cfOld = (string) old('cf_activity', (string) (data_get($account, 'cf_activity') ?? '')); @endphp
                <select name="cf_activity" class="border rounded p-2 w-full">
                    <option value="" {{ $cfOld === '' ? 'selected' : '' }}>— Default (Operating) —</option>
                    <option value="cash" {{ $cfOld === 'cash' ? 'selected' : '' }}>cash</option>
                    <option value="operating" {{ $cfOld === 'operating' ? 'selected' : '' }}>operating</option>
                    <option value="investing" {{ $cfOld === 'investing' ? 'selected' : '' }}>investing</option>
                    <option value="financing" {{ $cfOld === 'financing' ? 'selected' : '' }}>financing</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Dipakai oleh report Arus Kas (Cash Flow).</p>
            </div>

            {{-- PARENT ACCOUNT --}}
            <div>
                <label class="block text-sm mb-1">Parent Account (optional)</label>
                @php
                    $parentIdOld = (string) old('parent_id', (string) (data_get($account, 'parent_id') ?? ''));
                    $selfId = (string) data_get($account, 'id', '');

                    $parentsFiltered = collect($parents ?? [])
                        ->filter(function ($p) use ($type, $selfId) {
                            if (!is_array($p)) {
                                return false;
                            }
                            $pid = (string) data_get($p, 'id', '');
                            if ($pid === '' || $pid === $selfId) {
                                return false;
                            }
                            return (string) data_get($p, 'type', '') === $type;
                        })
                        ->values();

                    $parentExists = $parentIdOld !== '' && $parentsFiltered->contains(function ($p) use ($parentIdOld) {
                        return (string) data_get($p, 'id', '') === $parentIdOld;
                    });
                @endphp

                <select name="parent_id" class="border rounded p-2 w-full">
                    <option value="" {{ $parentIdOld === '' ? 'selected' : '' }}>— No Parent —</option>

                    @if ($parentIdOld !== '' && !$parentExists)
                        <option value="{{ $parentIdOld }}" selected>Parent saat ini: {{ $parentIdOld }}</option>
                    @endif

                    @foreach ($parentsFiltered as $p)
                        @php
                            $pid = (string) data_get($p, 'id', '');
                            $label = trim((string) data_get($p, 'code', '') . ' — ' . (string) data_get($p, 'name', ''));
                            $isPostable = (bool) data_get($p, 'is_postable', true);
                            $suffix = $isPostable ? '' : ' (header)';
                        @endphp
                        <option value="{{ $pid }}" {{ $parentIdOld === $pid ? 'selected' : '' }}>
                            {{ $label }}{{ $suffix }}
                        </option>
                    @endforeach
                </select>

                <p class="text-xs text-gray-500 mt-1">
                    Pilih akun parent (biasanya akun header/grup). Untuk melepas parent, pilih “No Parent”.
                </p>
            </div>

            <div>
                <label class="block text-sm mb-1">Type</label>
                <input readonly class="border rounded w-full px-3 py-2 bg-gray-100 cursor-not-allowed"
                    value="{{ $type }}">

                <label class="block text-sm mt-4 mb-1">Normal Balance</label>
                <input readonly class="border rounded w-full px-3 py-2 bg-gray-100 cursor-not-allowed"
                    value="{{ $normalBalance }}">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="border rounded p-3">
                    <input type="hidden" name="is_postable" value="0">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="is_postable" value="1" class="rounded"
                            {{ $postableOld === '1' ? 'checked' : '' }}>
                        <span class="text-sm font-medium">Postable</span>
                    </label>
                    @if ($hasTransactions)
                        <p class="text-xs text-gray-500 mt-1">Akun ini sudah punya transaksi; perubahan mungkin ditolak oleh backend.</p>
                    @else
                        <p class="text-xs text-gray-500 mt-1">Bisa diubah jika belum ada transaksi.</p>
                    @endif
                </div>

                <div class="border rounded p-3">
                    <input type="hidden" name="is_active" value="0">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" class="rounded"
                            {{ $activeOld === '1' ? 'checked' : '' }}>
                        <span class="text-sm font-medium">Active</span>
                    </label>
                </div>
            </div>

            <div class="border rounded p-3 space-y-3">
                <div>
                    <input type="hidden" name="requires_bp" value="0">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="requires_bp" value="1" class="rounded"
                            {{ $requiresBpOld === '1' ? 'checked' : '' }}>
                        <span class="text-sm font-medium">Requires Business Partner</span>
                    </label>
                </div>

                <div>
                    <label class="block text-sm mb-1">Subledger (optional)</label>
                    <select name="subledger" class="border rounded p-2 w-full">
                        <option value="" {{ $subledgerOld === '' ? 'selected' : '' }}>— None —</option>
                        <option value="ar" {{ $subledgerOld === 'ar' ? 'selected' : '' }}>ar</option>
                        <option value="ap" {{ $subledgerOld === 'ap' ? 'selected' : '' }}>ap</option>
                    </select>
                </div>
            </div>

            <div class="flex gap-2">
                <button class="px-4 py-2 rounded bg-black text-white">Update</button>
                <a class="px-4 py-2 rounded border" href="{{ route('finance.accounts.index') }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection

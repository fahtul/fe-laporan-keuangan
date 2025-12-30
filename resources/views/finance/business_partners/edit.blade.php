@extends('finance.layout')
@section('title', 'Edit Business Partner')
@section('subtitle', 'Update data pihak')

@section('header_actions')
    <a href="{{ route('finance.business_partners.index') }}" class="px-4 py-2 rounded border">Back</a>
@endsection

@section('content')
    <div class="bg-white border rounded p-4 space-y-4">
        @if (session('success'))
            <div class="p-3 rounded bg-green-100 text-green-800">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="p-3 rounded bg-red-100 text-red-800">
                <ul class="list-disc ml-5 text-sm">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="text-xs text-gray-500">ID</div>
                <div class="font-mono text-sm">{{ $item['id'] ?? '' }}</div>
            </div>
        </div>

        <form id="bpUpdateForm" method="POST" action="{{ route('finance.business_partners.update', $item['id']) }}"
            class="space-y-4">
            @csrf
            @method('PUT')

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm mb-1">Code</label>
                    <input id="bpCode" name="code" value="{{ old('code', $item['code'] ?? '') }}"
                        class="border rounded w-full px-3 py-2" required>
                </div>
                <div>
                    <label class="block text-sm mb-1">Nama</label>
                    <input name="name" value="{{ old('name', $item['name'] ?? '') }}"
                        class="border rounded w-full px-3 py-2" required>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm mb-1">Kategori</label>
                    @php $catVal = old('category', $item['category'] ?? 'other'); @endphp
                    <select name="category" class="border rounded w-full px-3 py-2" id="bpCategory">
                        @foreach ($categoryOptions as $k => $label)
                            <option value="{{ $k }}"
                                {{ (string) $catVal === (string) $k ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm mb-1">Normal Balance</label>
                    @php
                        $normalVal = old('normal_balance', $item['normal_balance'] ?? null);
                        if ($normalVal === null) {
                            $normalVal = $catVal === 'supplier' ? 'credit' : 'debit';
                        }
                    @endphp
                    <select name="normal_balance" class="border rounded w-full px-3 py-2" id="bpNormalBalance">
                        @foreach ($normalBalanceOptions as $k => $label)
                            <option value="{{ $k }}"
                                {{ (string) $normalVal === (string) $k ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1"
                    {{ old('is_active', $item['is_active'] ?? true) ? 'checked' : '' }}>
                <span>Aktif</span>
            </label>
        </form>

        <div class="flex gap-2">
            <button type="submit" form="bpUpdateForm" class="px-4 py-2 rounded bg-black text-white">Update</button>

            <form method="POST" action="{{ route('finance.business_partners.destroy', $item['id']) }}"
                onsubmit="return confirm('Hapus business partner ini (soft delete)?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 rounded border border-red-300 text-red-700">
                    Hapus
                </button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const code = document.getElementById('bpCode');
            const category = document.getElementById('bpCategory');
            const normal = document.getElementById('bpNormalBalance');

            let normalTouched = @json(old('normal_balance') !== null);

            function defaultNormalBalance(cat) {
                if (cat === 'supplier') return 'credit';
                if (cat === 'customer') return 'debit';
                if (cat === 'insurer') return 'debit';
                return 'debit';
            }

            if (code) {
                code.addEventListener('input', function() {
                    const start = code.selectionStart;
                    const end = code.selectionEnd;
                    code.value = (code.value || '').toUpperCase();
                    try {
                        code.setSelectionRange(start, end);
                    } catch (e) {}
                });
            }

            if (normal) {
                normal.addEventListener('change', function() {
                    normalTouched = true;
                });
            }

            if (category && normal) {
                category.addEventListener('change', function() {
                    if (normalTouched) return;
                    normal.value = defaultNormalBalance(category.value);
                });
            }
        });
    </script>
@endsection

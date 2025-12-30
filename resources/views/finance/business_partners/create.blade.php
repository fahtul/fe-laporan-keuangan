@extends('finance.layout')
@section('title', 'Tambah Business Partner')
@section('subtitle', 'Buat pihak baru (supplier/pasien/asuransi/dll)')

@section('header_actions')
    <a href="{{ route('finance.business_partners.index') }}" class="px-4 py-2 rounded border">Back</a>
@endsection

@section('content')
    <div class="bg-white border rounded p-4 space-y-4">
        @if ($errors->any())
            <div class="p-3 rounded bg-red-100 text-red-800">
                <ul class="list-disc ml-5 text-sm">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Soft-deleted hint dari BE --}}
        @if (session('bp_error_code') === 'BP_SOFT_DELETED' && session('bp_restore_data'))
            @php $d = session('bp_restore_data'); @endphp
            <div class="p-3 rounded bg-yellow-100 text-yellow-900">
                <div class="font-semibold">Data pernah dibuat dan sedang terhapus.</div>
                <div class="text-sm mt-1">
                    ID: <span class="font-mono">{{ $d['id'] ?? '-' }}</span><br>
                    Code: <b>{{ $d['code'] ?? '-' }}</b><br>
                    Nama: <b>{{ $d['name'] ?? '-' }}</b>
                </div>

                @if (!empty($d['id']))
                    <form class="mt-3" method="POST" action="{{ route('finance.business_partners.restore', $d['id']) }}"
                        onsubmit="return confirm('Restore data ini?');">
                        @csrf
                        <button class="px-4 py-2 rounded bg-black text-white">Restore</button>
                    </form>
                @endif
            </div>
        @endif

        <form method="POST" action="{{ route('finance.business_partners.store') }}" class="space-y-4">
            @csrf

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm mb-1">Code</label>
                    <input id="bpCode" name="code" value="{{ old('code') }}" class="border rounded w-full px-3 py-2"
                        required>
                </div>
                <div>
                    <label class="block text-sm mb-1">Nama</label>
                    <input name="name" value="{{ old('name') }}" class="border rounded w-full px-3 py-2" required>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm mb-1">Kategori</label>
                    @php $catVal = old('category', 'other'); @endphp
                    <select name="category" class="border rounded w-full px-3 py-2" id="bpCategory">
                        @foreach ($categoryOptions as $k => $label)
                            <option value="{{ $k }}" {{ $catVal === $k ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm mb-1">Normal Balance</label>
                    @php
                        $normalVal = old('normal_balance');
                        if ($normalVal === null) {
                            $normalVal = $catVal === 'supplier' ? 'credit' : 'debit';
                        }
                    @endphp
                    <select name="normal_balance" class="border rounded w-full px-3 py-2" id="bpNormalBalance">
                        @foreach ($normalBalanceOptions as $k => $label)
                            <option value="{{ $k }}" {{ (string) $normalVal === $k ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                <span>Aktif</span>
            </label>

            <div class="flex gap-2">
                <button class="px-4 py-2 rounded bg-black text-white">Simpan</button>
                <a class="px-4 py-2 rounded border" href="{{ route('finance.business_partners.index') }}">Cancel</a>
            </div>
        </form>
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

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
                    <input name="code" value="{{ old('code') }}" class="border rounded w-full px-3 py-2" required>
                </div>
                <div>
                    <label class="block text-sm mb-1">Nama</label>
                    <input name="name" value="{{ old('name') }}" class="border rounded w-full px-3 py-2" required>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm mb-1">Kategori</label>
                    <select name="category" class="border rounded w-full px-3 py-2">
                        @foreach ($categoryOptions as $k => $label)
                            <option value="{{ $k }}" {{ old('category', 'other') === $k ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm mb-1">Normal Balance</label>
                    <select name="normal_balance" class="border rounded w-full px-3 py-2">
                        @foreach ($normalBalanceOptions as $k => $label)
                            <option value="{{ $k }}"
                                {{ old('normal_balance', 'debit') === $k ? 'selected' : '' }}>
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
@endsection

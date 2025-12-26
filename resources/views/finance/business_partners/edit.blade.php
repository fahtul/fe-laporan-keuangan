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

        <form method="POST" action="{{ route('finance.business_partners.update', $item['id']) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm mb-1">Code</label>
                    <input name="code" value="{{ old('code', $item['code'] ?? '') }}"
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
                    <select name="category" class="border rounded w-full px-3 py-2">
                        @foreach ($categoryOptions as $k => $label)
                            <option value="{{ $k }}"
                                {{ old('category', $item['category'] ?? 'other') === $k ? 'selected' : '' }}>
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
                                {{ old('normal_balance', $item['normal_balance'] ?? 'debit') === $k ? 'selected' : '' }}>
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

            <div class="flex gap-2">
                <button class="px-4 py-2 rounded bg-black text-white">Update</button>

                <form class="inline" method="POST" action="{{ route('finance.business_partners.destroy', $item['id']) }}"
                    onsubmit="return confirm('Hapus business partner ini (soft delete)?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-4 py-2 rounded border border-red-300 text-red-700">
                        Hapus
                    </button>
                </form>
            </div>
        </form>
    </div>
@endsection

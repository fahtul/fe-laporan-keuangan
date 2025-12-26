@extends('finance.layout')
@section('title', 'Business Partners')
@section('subtitle', 'Daftar pihak (supplier/pasien/asuransi/dll)')

@section('header_actions')
    <a href="{{ route('finance.business_partners.create') }}" class="px-4 py-2 rounded bg-black text-white">
        + Tambah
    </a>
@endsection

@section('content')
    <div class="bg-white border rounded p-4 space-y-4">
        @if (!empty($apiError))
            <div class="p-3 rounded bg-red-100 text-red-800">
                {{ $apiError }}
            </div>
        @endif

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

        <form class="grid md:grid-cols-5 gap-3" method="GET" action="{{ route('finance.business_partners.index') }}">
            <div class="md:col-span-2">
                <label class="block text-xs text-gray-500 mb-1">Cari</label>
                <input name="q" value="{{ $q }}" class="border rounded w-full px-3 py-2"
                    placeholder="Kode / Nama...">
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Kategori</label>
                <select name="category" class="border rounded w-full px-3 py-2">
                    <option value="">Semua</option>
                    @foreach ($categoryOptions as $k => $label)
                        <option value="{{ $k }}" {{ $category === $k ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Limit</label>
                <select name="limit" class="border rounded w-full px-3 py-2">
                    @foreach ([10, 20, 50, 100] as $n)
                        <option value="{{ $n }}" {{ (int) $limit === $n ? 'selected' : '' }}>{{ $n }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end gap-2">
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="include_inactive" value="true"
                        {{ $include_inactive === 'true' ? 'checked' : '' }}>
                    <span>Tampilkan non-aktif</span>
                </label>

                <button class="px-4 py-2 rounded bg-black text-white">Filter</button>
            </div>
        </form>

        <div class="overflow-x-auto border rounded">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="p-3 text-left">Kode</th>
                        <th class="p-3 text-left">Nama</th>
                        <th class="p-3 text-left">Kategori</th>
                        <th class="p-3 text-left">Normal</th>
                        <th class="p-3 text-left">Aktif</th>
                        <th class="p-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $it)
                        <tr class="border-t">
                            <td class="p-3 font-mono">{{ $it['code'] ?? '-' }}</td>
                            <td class="p-3">{{ $it['name'] ?? '-' }}</td>
                            <td class="p-3">
                                {{ $categoryOptions[$it['category'] ?? 'other'] ?? ($it['category'] ?? 'other') }}
                            </td>
                            <td class="p-3">
                                {{ $normalBalanceOptions[$it['normal_balance'] ?? 'debit'] ?? ($it['normal_balance'] ?? 'debit') }}
                            </td>
                            <td class="p-3">
                                @if (($it['is_active'] ?? true) === true)
                                    <span
                                        class="inline-flex px-2 py-1 rounded text-xs bg-green-100 text-green-800">Aktif</span>
                                @else
                                    <span
                                        class="inline-flex px-2 py-1 rounded text-xs bg-gray-100 text-gray-800">Non-aktif</span>
                                @endif
                            </td>
                            <td class="p-3 text-right whitespace-nowrap">
                                <a class="px-3 py-1.5 rounded border"
                                    href="{{ route('finance.business_partners.edit', $it['id']) }}">
                                    Edit
                                </a>

                                <form class="inline" method="POST"
                                    action="{{ route('finance.business_partners.destroy', $it['id']) }}"
                                    onsubmit="return confirm('Hapus business partner ini (soft delete)?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="px-3 py-1.5 rounded border border-red-300 text-red-700">
                                        Hapus
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr class="border-t">
                            <td class="p-4 text-gray-500" colspan="6">Tidak ada data</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $items->links() }}
        </div>
    </div>
@endsection

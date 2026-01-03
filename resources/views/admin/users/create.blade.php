<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Tambah User</h2>
                <div class="text-sm text-gray-600">Admin membuat user baru dan memberi role.</div>
            </div>
            <a href="{{ route('admin.users.index') }}"
                class="px-4 py-2 rounded border bg-white text-gray-900 hover:bg-gray-50">
                Kembali
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

            @if ($errors->any())
                <div class="mb-4 p-3 rounded bg-red-50 border border-red-200 text-red-800">
                    <div class="font-semibold">Ada error:</div>
                    <ul class="list-disc pl-5 mt-1">
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white border rounded p-6">
                <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama</label>
                        <input name="name" value="{{ old('name') }}"
                            class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Nama user" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}"
                            class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="user@domain.com" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <select name="role"
                            class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required>
                            @foreach ($roles as $r)
                                <option value="{{ $r }}" @selected(old('role', 'viewer') === $r)>{{ $r }}</option>
                            @endforeach
                        </select>
                        <div class="text-xs text-gray-500 mt-1">admin / accountant / viewer</div>
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="hidden" name="is_active" value="0">
                        <input id="is_active" type="checkbox" name="is_active" value="1"
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                            @checked(old('is_active', '1') === '1')>
                        <label for="is_active" class="text-sm text-gray-700">Aktif</label>
                    </div>

                    <div class="pt-2 flex items-center justify-end gap-2">
                        <a href="{{ route('admin.users.index') }}"
                            class="px-4 py-2 rounded border bg-white text-gray-900 hover:bg-gray-50">Batal</a>
                        <button class="px-4 py-2 rounded bg-indigo-600 text-white hover:bg-indigo-700">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>

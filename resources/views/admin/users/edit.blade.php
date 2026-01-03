<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit User</h2>
                <div class="text-sm text-gray-600">{{ $user->email }}</div>
            </div>
            <a href="{{ route('admin.users.index') }}"
                class="px-4 py-2 rounded border bg-white text-gray-900 hover:bg-gray-50">
                Kembali
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('success'))
                <div class="p-3 rounded bg-green-100 text-green-800">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="p-3 rounded bg-red-100 text-red-800">{{ session('error') }}</div>
            @endif
            @if (session('generated_password'))
                <div class="p-3 rounded bg-amber-50 border border-amber-200 text-amber-900">
                    <div class="font-semibold">Password sementara</div>
                    <div class="font-mono break-all">{{ session('generated_password') }}</div>
                    <div class="text-sm mt-1 text-amber-800">Catat sekarang. Password ini hanya tampil sekali.</div>
                </div>
            @endif
            @if ($errors->any())
                <div class="p-3 rounded bg-red-50 border border-red-200 text-red-800">
                    <div class="font-semibold">Ada error:</div>
                    <ul class="list-disc pl-5 mt-1">
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white border rounded p-6">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <div class="text-sm text-gray-600">
                        Status:
                        @if ($user->is_active)
                            <span class="px-2 py-1 rounded bg-green-100 text-green-800">Active</span>
                        @else
                            <span class="px-2 py-1 rounded bg-red-100 text-red-800">Inactive</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        <form method="POST" action="{{ route('admin.users.toggle', $user) }}"
                            onsubmit="return confirm('Ubah status aktif user ini?')">
                            @csrf
                            @method('PATCH')
                            <button
                                class="px-3 py-2 rounded {{ $user->is_active ? 'bg-red-600' : 'bg-green-600' }} text-white">
                                {{ $user->is_active ? 'Disable' : 'Enable' }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.users.reset', $user) }}"
                            onsubmit="return confirm('Reset password user ini? Password baru akan muncul sekali.');">
                            @csrf
                            @method('PATCH')
                            <button class="px-3 py-2 rounded bg-gray-900 text-white hover:bg-black">
                                Reset Password
                            </button>
                        </form>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-4 mt-6">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama</label>
                        <input name="name" value="{{ old('name', $user->name) }}"
                            class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}"
                            class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <select name="role"
                            class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required>
                            @foreach ($roles as $r)
                                <option value="{{ $r }}" @selected(old('role', $user->role ?? 'viewer') === $r)>{{ $r }}</option>
                            @endforeach
                        </select>
                        <div class="text-xs text-gray-500 mt-1">Tidak bisa menurunkan role admin terakhir.</div>
                    </div>

                    <div class="pt-2 flex items-center justify-end gap-2">
                        <a href="{{ route('admin.users.index') }}"
                            class="px-4 py-2 rounded border bg-white text-gray-900 hover:bg-gray-50">Kembali</a>
                        <button class="px-4 py-2 rounded bg-indigo-600 text-white hover:bg-indigo-700">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>


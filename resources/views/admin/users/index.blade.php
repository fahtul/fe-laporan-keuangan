<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Admin Users</h2>
                <div class="text-sm text-gray-600">Kelola user, role, dan status aktif.</div>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('admin.users.create') }}"
                    class="px-4 py-2 rounded bg-indigo-600 text-white hover:bg-indigo-700">
                    Tambah User
                </a>

                <form method="GET" action="{{ route('admin.users.index') }}" class="flex gap-2">
                    <input name="q" value="{{ $q }}" placeholder="Cari name/email..."
                        class="border rounded px-3 py-2 w-64" />
                    <button class="px-4 py-2 rounded border bg-white text-gray-900 hover:bg-gray-50">Cari</button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 p-3 rounded bg-green-100 text-green-800">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-4 p-3 rounded bg-red-100 text-red-800">{{ session('error') }}</div>
            @endif
            @if (session('generated_password'))
                <div class="mb-4 p-3 rounded bg-amber-50 border border-amber-200 text-amber-900">
                    <div class="font-semibold">Password sementara</div>
                    <div class="font-mono break-all">{{ session('generated_password') }}</div>
                    <div class="text-sm mt-1 text-amber-800">Catat sekarang. Password ini hanya tampil sekali.</div>
                </div>
            @endif
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

            <div class="overflow-x-auto bg-white border rounded">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left p-3">Name</th>
                            <th class="text-left p-3">Email</th>
                            <th class="text-left p-3">Role</th>
                            <th class="text-left p-3">Active</th>
                            <th class="text-right p-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $u)
                            <tr class="border-t">
                                <td class="p-3">{{ $u->name }}</td>
                                <td class="p-3">{{ $u->email }}</td>
                                <td class="p-3">
                                    <span class="px-2 py-1 rounded bg-gray-100">{{ $u->role ?? 'viewer' }}</span>
                                </td>
                                <td class="p-3">
                                    @if ($u->is_active)
                                        <span class="px-2 py-1 rounded bg-green-100 text-green-800">Active</span>
                                    @else
                                        <span class="px-2 py-1 rounded bg-red-100 text-red-800">Inactive</span>
                                    @endif
                                </td>
                                <td class="p-3 text-right">
                                    <div class="inline-flex items-center gap-2 justify-end">
                                        <a href="{{ route('admin.users.edit', $u) }}"
                                            class="px-3 py-2 rounded border bg-white text-gray-900 hover:bg-gray-50">
                                            Edit
                                        </a>

                                        <form method="POST" action="{{ route('admin.users.toggle', $u) }}"
                                            onsubmit="return confirm('Ubah status aktif user ini?')">
                                            @csrf
                                            @method('PATCH')
                                            <button
                                                class="px-3 py-2 rounded {{ $u->is_active ? 'bg-red-600' : 'bg-green-600' }} text-white">
                                                {{ $u->is_active ? 'Disable' : 'Enable' }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.users.reset', $u) }}"
                                            onsubmit="return confirm('Reset password user ini? Password baru akan muncul sekali.');">
                                            @csrf
                                            @method('PATCH')
                                            <button class="px-3 py-2 rounded bg-gray-900 text-white hover:bg-black">
                                                Reset Password
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $users->links() }}
            </div>

        </div>
    </div>
</x-app-layout>

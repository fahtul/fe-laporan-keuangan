<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Admin - Users
            </h2>

            <form method="GET" action="{{ route('admin.users.index') }}" class="flex gap-2">
                <input name="q" value="{{ $q }}" placeholder="Search name/email..."
                    class="border rounded px-3 py-2 w-64" />
                <button class="px-4 py-2 rounded bg-black text-white">Search</button>
            </form>
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

            <div class="overflow-x-auto bg-white border rounded">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left p-3">Name</th>
                            <th class="text-left p-3">Email</th>
                            <th class="text-left p-3">Role</th>
                            <th class="text-left p-3">Active</th>
                            <th class="text-right p-3">Action</th>
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
                                    <form method="POST" action="{{ route('admin.users.toggleActive', $u) }}">
                                        @csrf
                                        <button
                                            class="px-3 py-2 rounded {{ $u->is_active ? 'bg-red-600' : 'bg-green-600' }} text-white">
                                            {{ $u->is_active ? 'Disable' : 'Enable' }}
                                        </button>
                                    </form>
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

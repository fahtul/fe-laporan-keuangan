<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    @yield('title', 'Finance')
                </h2>
                <p class="text-sm text-gray-500">@yield('subtitle')</p>
            </div>

            <div class="flex gap-2">
                @yield('header_actions')
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">

            {{-- Flash + Errors --}}
            @if(session('success'))
                <div class="p-3 rounded bg-green-100 text-green-800">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="p-3 rounded bg-red-100 text-red-800">
                    <ul class="list-disc ml-5">
                        @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
                    </ul>
                </div>
            @endif

            {{-- Content --}}
            @yield('content')
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <style>
            .report-sticky {
                position: sticky;
                top: 0;
                z-index: 10;
                backdrop-filter: blur(6px);
            }

            .report-chip {
                display: inline-flex;
                align-items: center;
                gap: .375rem;
                padding: .25rem .5rem;
                border-radius: .5rem;
                border: 1px solid rgba(0, 0, 0, .08);
                background: rgba(255, 255, 255, .75);
                font-size: .75rem;
                color: #374151;
            }
        </style>

        <div class="report-sticky bg-white/80 border-b">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 py-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="font-semibold text-xl text-gray-800 leading-tight truncate">
                            @yield('title', 'Finance Report')
                        </h2>
                        <p class="text-sm text-gray-500 mt-0.5">@yield('subtitle')</p>

                        @hasSection('header_meta')
                            <div class="mt-2 flex flex-wrap gap-2">
                                @yield('header_meta')
                            </div>
                        @endif
                    </div>

                    <div class="flex gap-2 flex-wrap justify-end">
                        @yield('header_actions')
                    </div>
                </div>

                @hasSection('tools')
                    <div class="mt-4">
                        @yield('tools')
                    </div>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">

            {{-- Flash + Errors (sama seperti layout lama) --}}
            @if (session('success'))
                <div class="p-3 rounded bg-green-100 text-green-800">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="p-3 rounded bg-red-100 text-red-800">
                    <ul class="list-disc ml-5">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </div>
    </div>

    @hasSection('scripts')
        @yield('scripts')
    @endif
</x-app-layout>

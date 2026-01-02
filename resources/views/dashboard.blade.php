@php
    $user = auth()->user();
    $role = (string) ($user?->role ?? 'viewer');
    $isActive = (bool) ($user?->is_active ?? true);
    $canManage = in_array($role, ['admin', 'accountant'], true);

    $nowYear = (int) now()->format('Y');
    $year = (string) request()->query('year', (string) $nowYear);
    if (!preg_match('/^\d{4}$/', $year)) {
        $year = (string) $nowYear;
    }
    $years = range($nowYear - 5, $nowYear + 1);

    $routeHas = fn(string $name) => \Illuminate\Support\Facades\Route::has($name);
    $routeUrl = function (string $name, array $params = []) use ($routeHas) {
        return $routeHas($name) ? route($name, $params) : '#'; // TODO: set route name
    };

    $stats = is_array($stats ?? null) ? $stats : [];
    $fmtRupiah = function ($val) {
        if ($val === null || $val === '') {
            return '0';
        }
        return 'Rp ' . number_format((float) $val, 0, ',', '.');
    };

    $icon = function (string $name) {
        $svgs = [
            'report' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a2 2 0 012-2h2a2 2 0 012 2v2M7 3h10a2 2 0 012 2v14a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"/></svg>',
            'cash' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12h-6a2 2 0 00-2 2v2a2 2 0 002 2h6v-6z"/></svg>',
            'ledger' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>',
            'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 21v-1a4 4 0 00-4-4H6a4 4 0 00-4 4v1"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 7a4 4 0 110 8 4 4 0 010-8z"/></svg>',
            'settings' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317a1 1 0 011.35-.936l1.09.36a1 1 0 00.74 0l1.09-.36a1 1 0 011.35.936l.17 1.14a1 1 0 00.37.6l.88.72a1 1 0 010 1.55l-.88.72a1 1 0 00-.37.6l-.17 1.14a1 1 0 01-1.35.936l-1.09-.36a1 1 0 00-.74 0l-1.09.36a1 1 0 01-1.35-.936l-.17-1.14a1 1 0 00-.37-.6l-.88-.72a1 1 0 010-1.55l.88-.72a1 1 0 00.37-.6l.17-1.14z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11a1 1 0 100 2 1 1 0 000-2z"/></svg>',
            'plus' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg>',
            'lock' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11V7a4 4 0 118 0v4"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 11h12a2 2 0 012 2v7a2 2 0 01-2 2H6a2 2 0 01-2-2v-7a2 2 0 012-2z"/></svg>',
        ];

        return $svgs[$name] ?? $svgs['report'];
    };

    $menus = [
        // Laporan
        ['key' => 'trial_balance', 'title' => 'Neraca Saldo', 'desc' => 'Trial balance per periode.', 'route' => 'finance.trial_balance.index', 'params' => ['year' => $year], 'section' => 'Laporan', 'icon' => 'report', 'keywords' => 'neraca saldo trial balance'],
        ['key' => 'balance_sheet', 'title' => 'Neraca', 'desc' => 'Balance sheet / posisi keuangan.', 'route' => 'finance.balance_sheet.index', 'params' => ['year' => $year], 'section' => 'Laporan', 'icon' => 'report', 'keywords' => 'neraca balance sheet'],
        ['key' => 'worksheet', 'title' => 'Neraca Lajur', 'desc' => 'Worksheet / neraca lajur.', 'route' => 'finance.worksheet.index', 'params' => ['year' => $year], 'section' => 'Laporan', 'icon' => 'report', 'keywords' => 'neraca lajur worksheet'],
        ['key' => 'income_statement', 'title' => 'Laba Rugi', 'desc' => 'Laporan laba rugi.', 'route' => 'finance.income_statement.index', 'params' => ['year' => $year], 'section' => 'Laporan', 'icon' => 'report', 'keywords' => 'laba rugi income statement'],
        ['key' => 'cash_flow', 'title' => 'Arus Kas', 'desc' => 'Laporan arus kas.', 'route' => 'finance.cash_flow.index', 'params' => ['year' => $year], 'section' => 'Laporan', 'icon' => 'cash', 'keywords' => 'arus kas cash flow'],
        ['key' => 'equity_statement', 'title' => 'LP Ekuitas', 'desc' => 'Laporan perubahan ekuitas.', 'route' => 'finance.equity_statement.index', 'params' => ['year' => $year], 'section' => 'Laporan', 'icon' => 'report', 'keywords' => 'ekuitas equity changes'],

        // Transaksi
        ['key' => 'journal_entries', 'title' => 'Jurnal', 'desc' => 'Daftar jurnal draft/posted/void.', 'route' => 'finance.journal_entries.index', 'section' => 'Transaksi', 'icon' => 'ledger', 'keywords' => 'jurnal journal entries'],
        ['key' => 'journal_entries_create', 'title' => 'Buat Jurnal', 'desc' => 'Input jurnal baru.', 'route' => 'finance.journal_entries.create', 'section' => 'Transaksi', 'icon' => 'plus', 'keywords' => 'buat jurnal', 'manage' => true],
        ['key' => 'closings', 'title' => 'Tutup Buku', 'desc' => 'Year-end closing + opening.', 'route' => 'finance.closings.year_end.index', 'params' => ['year' => $year], 'section' => 'Transaksi', 'icon' => 'lock', 'keywords' => 'tutup buku closing'],
        ['key' => 'opening_balances', 'title' => 'Opening Balance', 'desc' => 'Saldo awal per tahun.', 'route' => 'finance.opening_balances.index', 'params' => ['year' => $year], 'section' => 'Transaksi', 'icon' => 'ledger', 'keywords' => 'opening balance saldo awal'],
        ['key' => 'opening_balances_create', 'title' => 'Buat Opening Balance', 'desc' => 'Generate saldo awal.', 'route' => 'finance.opening_balances.create', 'params' => ['year' => $year], 'section' => 'Transaksi', 'icon' => 'plus', 'keywords' => 'buat opening balance', 'manage' => true],
        ['key' => 'ledgers', 'title' => 'Buku Besar', 'desc' => 'General ledger per akun.', 'route' => 'finance.ledgers.index', 'params' => ['year' => $year], 'section' => 'Transaksi', 'icon' => 'ledger', 'keywords' => 'buku besar general ledger'],
        ['key' => 'subledgers', 'title' => 'Buku Pembantu', 'desc' => 'Subledger AR/AP per BP.', 'route' => 'finance.subledgers.index', 'params' => ['year' => $year], 'section' => 'Transaksi', 'icon' => 'users', 'keywords' => 'buku pembantu subledger ar ap'],

        // Master Data
        ['key' => 'accounts', 'title' => 'Akun (COA)', 'desc' => 'Chart of Accounts.', 'route' => 'finance.accounts.index', 'section' => 'Master Data', 'icon' => 'ledger', 'keywords' => 'akun coa accounts'],
        ['key' => 'accounts_create', 'title' => 'Buat Akun', 'desc' => 'Tambah akun baru.', 'route' => 'finance.accounts.create', 'section' => 'Master Data', 'icon' => 'plus', 'keywords' => 'buat akun coa', 'manage' => true],
        ['key' => 'business_partners', 'title' => 'Business Partner', 'desc' => 'Customer/supplier/insurer.', 'route' => 'finance.business_partners.index', 'section' => 'Master Data', 'icon' => 'users', 'keywords' => 'business partner pelanggan supplier'],
        ['key' => 'business_partners_create', 'title' => 'Buat Business Partner', 'desc' => 'Tambah partner baru.', 'route' => 'finance.business_partners.create', 'section' => 'Master Data', 'icon' => 'plus', 'keywords' => 'buat business partner', 'manage' => true],

        // Setup
        ['key' => 'cashflow_mapping', 'title' => 'Mapping Arus Kas', 'desc' => 'Set kategori arus kas akun.', 'route' => 'finance.accounts.cashflow_mapping.index', 'section' => 'Setup', 'icon' => 'settings', 'keywords' => 'mapping arus kas cashflow', 'manage' => true],
    ];

    $sections = [
        ['key' => 'Laporan', 'expanded' => true],
        ['key' => 'Transaksi', 'expanded' => true],
        ['key' => 'Master Data', 'expanded' => false],
        ['key' => 'Setup', 'expanded' => false],
    ];

    $menusBySection = collect($menus)->groupBy('section');

    $quickActionKeys = ['journal_entries_create', 'closings', 'opening_balances_create'];
    $quickActions = collect($menus)->whereIn('key', $quickActionKeys)->values()->all();
@endphp

<x-app-layout>
    <x-slot name="header">
        <style>
            .chip { display: inline-flex; align-items: center; gap: .375rem; padding: .25rem .5rem; border-radius: .75rem; border: 1px solid rgba(0,0,0,.08); background: rgba(255,255,255,.75); font-size: .75rem; color: #374151; }
        </style>

        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Beranda</h2>
                <p class="text-sm text-gray-500 mt-0.5">Pilih menu di bawah untuk mulai bekerja.</p>

                <div class="mt-3 flex flex-wrap gap-2">
                    <span class="chip">Role: <span class="font-semibold">{{ $role }}</span></span>
                    <span class="chip {{ $isActive ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200' }}">
                        {{ $isActive ? 'Aktif' : 'Non-aktif' }}
                    </span>
                </div>
            </div>

            <div class="flex items-center gap-2 flex-wrap justify-end">
                <form method="GET" action="{{ route('dashboard') }}" class="flex items-center gap-2">
                    <label class="text-sm text-gray-600" for="yearSelect">Tahun</label>
                    <select id="yearSelect" name="year" class="border rounded px-3 py-2 bg-white">
                        @foreach ($years as $y)
                            <option value="{{ $y }}" {{ (string) $y === $year ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="px-3 py-2 rounded bg-gray-900 text-white">Terapkan</button>
                </form>

                <a class="px-4 py-2 rounded border bg-white text-gray-900 hover:bg-gray-50" href="{{ route('profile.edit') }}">Profil</a>
                @if ($role === 'admin' && $routeHas('admin.users.index'))
                    <a class="px-4 py-2 rounded border bg-white text-gray-900 hover:bg-gray-50" href="{{ route('admin.users.index') }}">Admin Users</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            {{-- Search --}}
            <div class="bg-white border rounded-lg p-6">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <div class="min-w-0">
                        <div class="text-gray-900 font-semibold truncate">Halo, {{ $user?->name }}</div>
                        <div class="text-sm text-gray-600 truncate">{{ $user?->email }}</div>
                    </div>
                    <div class="text-sm text-gray-500">Tip: tekan <span class="font-mono">/</span> untuk fokus.</div>
                </div>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="menuSearch">Cari menu…</label>
                    <input id="menuSearch" type="text"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="Contoh: neraca, buku besar, jurnal…"
                        autocomplete="off">
                    <div class="text-xs text-gray-500 mt-1">Hasil akan memfilter kartu menu di bawah.</div>
                </div>
            </div>

            {{-- KPI --}}
            @php
                $periodStatus = (string) (data_get($stats, 'period_status') ?? '');
                $periodLabel = $periodStatus !== '' ? $periodStatus : 'N/A';
                $periodHelp = $periodStatus !== '' ? '' : 'belum ada data';
            @endphp
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
                <div class="bg-white border rounded-lg p-4">
                    <div class="text-xs text-gray-500">Tahun Aktif</div>
                    <div class="text-lg font-semibold text-gray-900">{{ $year }}</div>
                    <div class="text-xs text-gray-500 mt-1">filter menu/report</div>
                </div>
                <div class="bg-white border rounded-lg p-4">
                    <div class="text-xs text-gray-500">Status Periode</div>
                    <div class="text-lg font-semibold text-gray-900">{{ $periodLabel }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ $periodHelp !== '' ? $periodHelp : '—' }}</div>
                </div>
                <div class="bg-white border rounded-lg p-4">
                    <div class="text-xs text-gray-500">Cash</div>
                    <div class="text-lg font-semibold text-gray-900">{{ $fmtRupiah(data_get($stats, 'cash')) }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ data_get($stats, 'cash') === null ? 'belum ada data' : '—' }}</div>
                </div>
                <div class="bg-white border rounded-lg p-4">
                    <div class="text-xs text-gray-500">AR</div>
                    <div class="text-lg font-semibold text-gray-900">{{ $fmtRupiah(data_get($stats, 'ar')) }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ data_get($stats, 'ar') === null ? 'belum ada data' : '—' }}</div>
                </div>
                <div class="bg-white border rounded-lg p-4">
                    <div class="text-xs text-gray-500">AP</div>
                    <div class="text-lg font-semibold text-gray-900">{{ $fmtRupiah(data_get($stats, 'ap')) }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ data_get($stats, 'ap') === null ? 'belum ada data' : '—' }}</div>
                </div>
                <div class="bg-white border rounded-lg p-4">
                    <div class="text-xs text-gray-500">Profit YTD</div>
                    <div class="text-lg font-semibold text-gray-900">{{ $fmtRupiah(data_get($stats, 'profit_ytd')) }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ data_get($stats, 'profit_ytd') === null ? 'belum ada data' : '—' }}</div>
                </div>
            </div>

            {{-- Favorit --}}
            <div class="bg-white border rounded-lg p-6">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <div>
                        <div class="font-semibold text-gray-900">Favorit</div>
                        <div class="text-sm text-gray-600">Pin menu dengan tombol ⭐.</div>
                    </div>
                    <button type="button" id="clearFavBtn" class="px-3 py-2 rounded border bg-white text-gray-900 hover:bg-gray-50">Clear</button>
                </div>

                <div id="favEmpty" class="mt-4 p-4 rounded border bg-gray-50 text-gray-700">
                    Belum ada favorit. Pin menu dengan ⭐ untuk akses cepat.
                </div>
                <div id="favGrid" class="hidden mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"></div>
            </div>

            {{-- Quick Actions --}}
            <div class="bg-white border rounded-lg p-6">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <div>
                        <div class="font-semibold text-gray-900">Quick Actions</div>
                        <div class="text-sm text-gray-600">Aksi yang paling sering dipakai.</div>
                    </div>
                    @if (!$canManage)
                        <div class="text-sm text-gray-500">Mode read-only (viewer).</div>
                    @endif
                </div>

                <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach ($quickActions as $m)
                        @php
                            $needsManage = (bool) ($m['manage'] ?? false);
                            $url = $routeUrl((string) $m['route'], (array) ($m['params'] ?? []));
                            $disabled = $needsManage && !$canManage;
                        @endphp
                        <a href="{{ $disabled ? '#' : $url }}"
                            class="group relative border rounded-lg p-4 bg-white hover:shadow-sm hover:border-indigo-200 {{ $disabled ? 'opacity-60 cursor-not-allowed' : '' }}"
                            data-menu-card="1"
                            data-key="{{ e((string) $m['key']) }}"
                            data-title="{{ e((string) $m['title']) }}"
                            data-desc="{{ e((string) $m['desc']) }}"
                            data-url="{{ e($disabled ? '' : $url) }}"
                            data-keywords="{{ e((string) ($m['keywords'] ?? '')) }}"
                            aria-label="{{ e((string) $m['title']) }}">
                            <div class="flex items-start gap-3">
                                <div class="shrink-0 rounded-md bg-indigo-50 p-2 text-indigo-700">{!! $icon((string) ($m['icon'] ?? 'report')) !!}</div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <div class="font-semibold text-gray-900 group-hover:text-indigo-700">{{ $m['title'] }}</div>
                                        <span class="pinnedBadge hidden text-[11px] px-2 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-200">Pinned</span>
                                    </div>
                                    <div class="text-sm text-gray-600 mt-1 truncate">{{ $m['desc'] }}</div>
                                    @if ($disabled)
                                        <div class="text-xs text-gray-500 mt-2">Hanya admin/accountant.</div>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Sections --}}
            @foreach ($sections as $sec)
                @php
                    $secKey = (string) $sec['key'];
                    $expanded = (bool) $sec['expanded'];
                    $items = $menusBySection->get($secKey, collect())->values()->all();
                @endphp

                <div class="bg-white border rounded-lg">
                    <button type="button"
                        class="w-full flex items-center justify-between px-6 py-4"
                        data-collapse-btn="1"
                        data-target="sec-{{ e($secKey) }}"
                        aria-expanded="{{ $expanded ? 'true' : 'false' }}">
                        <div class="text-left">
                            <div class="text-lg font-semibold text-gray-900">{{ $secKey }}</div>
                            <div class="text-sm text-gray-600">
                                {{ $secKey === 'Laporan' ? 'Laporan read-only.' : ($secKey === 'Transaksi' ? 'Transaksi & proses akuntansi.' : ($secKey === 'Master Data' ? 'Data dasar untuk transaksi.' : 'Pengaturan pendukung.')) }}
                            </div>
                        </div>
                        <div class="text-gray-400" aria-hidden="true">⌄</div>
                    </button>

                    <div id="sec-{{ e($secKey) }}" class="{{ $expanded ? '' : 'hidden' }} px-6 pb-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach ($items as $m)
                                @php
                                    $needsManage = (bool) ($m['manage'] ?? false);
                                    $url = $routeUrl((string) $m['route'], (array) ($m['params'] ?? []));
                                    $disabled = $needsManage && !$canManage;
                                @endphp
                                <a href="{{ $disabled ? '#' : $url }}"
                                    class="menuCard group relative border rounded-lg p-4 bg-white hover:shadow-sm hover:border-indigo-200 {{ $disabled ? 'opacity-60 cursor-not-allowed' : '' }}"
                                    data-menu-card="1"
                                    data-section="{{ e($secKey) }}"
                                    data-key="{{ e((string) $m['key']) }}"
                                    data-title="{{ e((string) $m['title']) }}"
                                    data-desc="{{ e((string) $m['desc']) }}"
                                    data-url="{{ e($disabled ? '' : $url) }}"
                                    data-keywords="{{ e((string) ($m['keywords'] ?? '')) }}"
                                    aria-label="{{ e((string) $m['title']) }}">
                                    <button type="button"
                                        class="favBtn absolute top-3 right-3 inline-flex items-center justify-center w-9 h-9 rounded-md border bg-white text-gray-500 hover:text-amber-600 hover:border-amber-200"
                                        title="Pin menu"
                                        aria-label="Pin menu"
                                        data-fav-btn="1"
                                        data-fav-key="{{ e((string) $m['key']) }}"
                                        aria-pressed="false">
                                        <span class="text-lg leading-none">★</span>
                                    </button>

                                    <div class="flex items-start gap-3 pr-10">
                                        <div class="shrink-0 rounded-md bg-indigo-50 p-2 text-indigo-700">{!! $icon((string) ($m['icon'] ?? 'report')) !!}</div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <div class="font-semibold text-gray-900 group-hover:text-indigo-700">{{ $m['title'] }}</div>
                                                <span class="pinnedBadge hidden text-[11px] px-2 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-200">Pinned</span>
                                            </div>
                                            <div class="text-sm text-gray-600 mt-1 truncate">{{ $m['desc'] }}</div>
                                            @if ($url === '#')
                                                {{-- TODO: set route name --}}
                                                <div class="text-xs text-gray-400 mt-2">(link belum diset)</div>
                                            @endif
                                            @if ($disabled)
                                                <div class="text-xs text-gray-500 mt-2">Hanya admin/accountant.</div>
                                            @endif
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const favKey = 'finance_home_favs';
            const maxFav = 6;

            const searchEl = document.getElementById('menuSearch');
            const favGrid = document.getElementById('favGrid');
            const favEmpty = document.getElementById('favEmpty');
            const clearFavBtn = document.getElementById('clearFavBtn');

            const cards = Array.from(document.querySelectorAll('[data-menu-card="1"]'))
                .filter(el => (el.dataset && el.dataset.key));

            function readFavs() {
                try {
                    const raw = localStorage.getItem(favKey);
                    const arr = raw ? JSON.parse(raw) : [];
                    return Array.isArray(arr) ? arr.map(String) : [];
                } catch (e) {
                    return [];
                }
            }

            function writeFavs(arr) {
                localStorage.setItem(favKey, JSON.stringify(arr));
            }

            function setPinnedUI(key, pinned) {
                document.querySelectorAll(`[data-fav-btn="1"][data-fav-key="${CSS.escape(key)}"]`).forEach(btn => {
                    btn.setAttribute('aria-pressed', pinned ? 'true' : 'false');
                    btn.classList.toggle('text-amber-600', pinned);
                    btn.classList.toggle('border-amber-200', pinned);
                });
                document.querySelectorAll(`[data-menu-card="1"][data-key="${CSS.escape(key)}"] .pinnedBadge`).forEach(b => {
                    b.classList.toggle('hidden', !pinned);
                });
            }

            function renderFavs() {
                if (!favGrid || !favEmpty) return;
                const favs = readFavs().slice(0, maxFav);

                favGrid.innerHTML = '';
                if (!favs.length) {
                    favEmpty.classList.remove('hidden');
                    favGrid.classList.add('hidden');
                    return;
                }

                favEmpty.classList.add('hidden');
                favGrid.classList.remove('hidden');

                favs.forEach(k => {
                    const src = document.querySelector(`[data-menu-card="1"][data-key="${CSS.escape(k)}"]`);
                    if (!src) return;
                    const clone = src.cloneNode(true);
                    // avoid nested pin button clicks navigating
                    favGrid.appendChild(clone);
                });
            }

            function toggleFav(key) {
                const favs = readFavs();
                const idx = favs.indexOf(key);
                if (idx >= 0) favs.splice(idx, 1);
                else favs.unshift(key);
                writeFavs(favs);
                setPinnedUI(key, favs.includes(key));
                renderFavs();
            }

            // init pinned UI
            readFavs().forEach(k => setPinnedUI(k, true));
            renderFavs();

            document.addEventListener('click', function (e) {
                const btn = e.target.closest('[data-fav-btn="1"]');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();
                const k = btn.getAttribute('data-fav-key') || '';
                if (!k) return;
                toggleFav(k);
            });

            if (clearFavBtn) {
                clearFavBtn.addEventListener('click', function () {
                    writeFavs([]);
                    cards.forEach(c => setPinnedUI(c.dataset.key, false));
                    renderFavs();
                });
            }

            // slash focus
            document.addEventListener('keydown', function (e) {
                if (e.key !== '/') return;
                const tag = (document.activeElement && document.activeElement.tagName || '').toLowerCase();
                if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
                if (!searchEl) return;
                e.preventDefault();
                searchEl.focus();
            });

            // search filter
            function applyFilter(q) {
                const qq = (q || '').trim().toLowerCase();
                cards.forEach(el => {
                    const title = (el.dataset.title || '').toLowerCase();
                    const desc = (el.dataset.desc || '').toLowerCase();
                    const keywords = (el.dataset.keywords || '').toLowerCase();
                    const hit = !qq || title.includes(qq) || desc.includes(qq) || keywords.includes(qq);
                    el.classList.toggle('hidden', !hit);
                });
            }
            if (searchEl) {
                searchEl.addEventListener('input', () => applyFilter(searchEl.value));
                applyFilter(searchEl.value);
            }

            // collapsible sections
            document.querySelectorAll('[data-collapse-btn="1"]').forEach(btn => {
                btn.addEventListener('click', function () {
                    const id = btn.getAttribute('data-target');
                    if (!id) return;
                    const panel = document.getElementById(id);
                    if (!panel) return;
                    const isOpen = !panel.classList.contains('hidden');
                    panel.classList.toggle('hidden', isOpen);
                    btn.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
                });
            });
        });
    </script>
</x-app-layout>


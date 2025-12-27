<x-app-layout>
    <x-slot name="header">
        @php
            $user = auth()->user();
            $role = $user->role ?? 'viewer';
            $isActive = (bool) ($user->is_active ?? true);
            $canWrite = in_array($role, ['admin', 'accountant'], true);
        @endphp

        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Beranda
                </h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    Pilih menu di bawah untuk mulai bekerja.
                </p>
            </div>

            <div class="flex gap-2 flex-wrap justify-end">
                <a class="px-4 py-2 rounded border bg-white" href="{{ route('profile.edit') }}">Profil</a>
                @if ($role === 'admin')
                    <a class="px-4 py-2 rounded border bg-white" href="{{ route('admin.users.index') }}">Admin Users</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white border shadow-sm sm:rounded-lg p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-gray-900 font-semibold truncate">
                            Halo, {{ $user->name }}
                        </div>
                        <div class="text-sm text-gray-600 truncate">
                            {{ $user->email }}
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border bg-gray-50 text-gray-700">
                            Role: {{ $role }}
                        </span>
                        <span
                            class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {{ $isActive ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200' }}">
                            {{ $isActive ? 'Aktif' : 'Non-aktif' }}
                        </span>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="menuSearch">Cari menu</label>
                    <input id="menuSearch" type="text"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="Contoh: neraca saldo, buku besar, journal, akun..." autocomplete="off">
                    <p class="text-xs text-gray-500 mt-1">Tips: kamu juga bisa buka lewat menu di navbar atas.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="menuGrid">
                {{-- Reports --}}
                <a class="menu-card group bg-white border rounded-lg p-4 hover:border-indigo-200 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    href="{{ route('finance.trial_balance.index') }}"
                    data-keywords="neraca saldo trial balance report laporan">
                    <div class="flex items-start gap-3">
                        <div class="shrink-0 rounded-md bg-indigo-50 p-2 text-indigo-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a2 2 0 012-2h2a2 2 0 012 2v2m-7 4h10a2 2 0 002-2V7a2 2 0 00-2-2H8a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-900 group-hover:text-indigo-700">Neraca Saldo</div>
                            <div class="text-sm text-gray-600">Trial balance per periode (read-only).</div>
                        </div>
                    </div>
                </a>

	                <a class="menu-card group bg-white border rounded-lg p-4 hover:border-indigo-200 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
	                    href="{{ route('finance.income_statement.index') }}"
	                    data-keywords="laba rugi income statement report laporan">
	                    <div class="flex items-start gap-3">
	                        <div class="shrink-0 rounded-md bg-indigo-50 p-2 text-indigo-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 11V3m0 8h8m-8 0l3-3m-3 3l-3-3M5 21h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                        </div>
	                        <div class="min-w-0">
	                            <div class="font-semibold text-gray-900 group-hover:text-indigo-700">Laba Rugi</div>
	                            <div class="text-sm text-gray-600">Income statement per periode.</div>
	                        </div>
	                    </div>
	                </a>

		                <a class="menu-card group bg-white border rounded-lg p-4 hover:border-indigo-200 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
		                    href="{{ route('finance.balance_sheet.index') }}"
		                    data-keywords="neraca balance sheet report laporan posisi per tanggal">
		                    <div class="flex items-start gap-3">
		                        <div class="shrink-0 rounded-md bg-indigo-50 p-2 text-indigo-700">
		                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
		                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m-6 4h6m-6 4h6M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
		                            </svg>
		                        </div>
		                        <div class="min-w-0">
		                            <div class="font-semibold text-gray-900 group-hover:text-indigo-700">Neraca</div>
		                            <div class="text-sm text-gray-600">Balance sheet (posisi per tanggal).</div>
		                        </div>
		                    </div>
		                </a>

		                <a class="menu-card group bg-white border rounded-lg p-4 hover:border-indigo-200 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
		                    href="{{ route('finance.cash_flow.index') }}"
		                    data-keywords="arus kas cash flow cfo cfi cff report laporan">
		                    <div class="flex items-start gap-3">
		                        <div class="shrink-0 rounded-md bg-indigo-50 p-2 text-indigo-700">
		                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
		                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-10V6m0 12v-2m9-4a9 9 0 11-18 0 9 9 0 0118 0z" />
		                            </svg>
		                        </div>
		                        <div class="min-w-0">
		                            <div class="font-semibold text-gray-900 group-hover:text-indigo-700">Arus Kas</div>
		                            <div class="text-sm text-gray-600">Cash flow (CFO/CFI/CFF).</div>
		                        </div>
		                    </div>
		                </a>

		                <a class="menu-card group bg-white border rounded-lg p-4 hover:border-indigo-200 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
		                    href="{{ route('finance.ledgers.index') }}"
		                    data-keywords="buku besar ledger general ledger report laporan">
		                    <div class="flex items-start gap-3">
		                        <div class="shrink-0 rounded-md bg-indigo-50 p-2 text-indigo-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h10M7 11h10M7 15h6M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-900 group-hover:text-indigo-700">Buku Besar</div>
                            <div class="text-sm text-gray-600">General ledger per akun dan periode.</div>
                        </div>
                    </div>
                </a>

                {{-- Transactions --}}
                <a class="menu-card group bg-white border rounded-lg p-4 hover:border-indigo-200 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    href="{{ route('finance.journal_entries.index') }}"
                    data-keywords="journal entries jurnal transaksi">
                    <div class="flex items-start gap-3">
                        <div class="shrink-0 rounded-md bg-emerald-50 p-2 text-emerald-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m2 10H7a2 2 0 01-2-2V6a2 2 0 012-2h7l5 5v11a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-900 group-hover:text-emerald-700">Journal Entries</div>
                            <div class="text-sm text-gray-600">Lihat, buat draft, post, dan reverse jurnal.</div>
                        </div>
                    </div>
                </a>

                @if ($canWrite)
                    <a class="menu-card group bg-white border rounded-lg p-4 hover:border-emerald-200 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        href="{{ route('finance.journal_entries.create') }}"
                        data-keywords="buat journal entry create jurnal transaksi">
                        <div class="flex items-start gap-3">
                            <div class="shrink-0 rounded-md bg-emerald-50 p-2 text-emerald-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <div class="font-semibold text-gray-900 group-hover:text-emerald-700">Buat Journal Entry</div>
                                <div class="text-sm text-gray-600">Buat draft atau langsung POST.</div>
                            </div>
                        </div>
                    </a>
                @endif

                <a class="menu-card group bg-white border rounded-lg p-4 hover:border-emerald-200 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    href="{{ route('finance.opening_balances.index') }}"
                    data-keywords="opening balance saldo awal">
                    <div class="flex items-start gap-3">
                        <div class="shrink-0 rounded-md bg-emerald-50 p-2 text-emerald-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 11h16M4 15h16M4 19h16" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-900 group-hover:text-emerald-700">Opening Balances</div>
                            <div class="text-sm text-gray-600">Lihat saldo awal per tahun.</div>
                        </div>
                    </div>
                </a>

                @if ($canWrite)
                    <a class="menu-card group bg-white border rounded-lg p-4 hover:border-emerald-200 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        href="{{ route('finance.opening_balances.create') }}"
                        data-keywords="buat opening balance saldo awal create">
                        <div class="flex items-start gap-3">
                            <div class="shrink-0 rounded-md bg-emerald-50 p-2 text-emerald-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <div class="font-semibold text-gray-900 group-hover:text-emerald-700">Buat Opening Balance</div>
                                <div class="text-sm text-gray-600">Input saldo awal tahun berjalan.</div>
                            </div>
                        </div>
                    </a>
                @endif

                {{-- Master data --}}
	                <a class="menu-card group bg-white border rounded-lg p-4 hover:border-sky-200 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
	                    href="{{ route('finance.accounts.index') }}"
	                    data-keywords="accounts akun coa master data">
	                    <div class="flex items-start gap-3">
	                        <div class="shrink-0 rounded-md bg-sky-50 p-2 text-sky-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-900 group-hover:text-sky-700">Accounts</div>
                            <div class="text-sm text-gray-600">Chart of accounts (COA).</div>
	                        </div>
	                    </div>
	                </a>

	                @if ($canWrite)
	                    <a class="menu-card group bg-white border rounded-lg p-4 hover:border-sky-200 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
	                        href="{{ route('finance.accounts.cashflow_mapping.index') }}"
	                        data-keywords="mapping arus kas cash flow coa cf activity">
	                        <div class="flex items-start gap-3">
	                            <div class="shrink-0 rounded-md bg-sky-50 p-2 text-sky-700">
	                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
	                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5l2 2h3a2 2 0 012 2v12a2 2 0 01-2 2z" />
	                                </svg>
	                            </div>
	                            <div class="min-w-0">
	                                <div class="font-semibold text-gray-900 group-hover:text-sky-700">Mapping Arus Kas (COA)</div>
	                                <div class="text-sm text-gray-600">Set kategori arus kas untuk akun.</div>
	                            </div>
	                        </div>
	                    </a>
	                @endif

	                @if ($canWrite)
	                    <a class="menu-card group bg-white border rounded-lg p-4 hover:border-sky-200 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
	                        href="{{ route('finance.accounts.create') }}"
	                        data-keywords="buat account akun coa create">
	                        <div class="flex items-start gap-3">
                            <div class="shrink-0 rounded-md bg-sky-50 p-2 text-sky-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <div class="font-semibold text-gray-900 group-hover:text-sky-700">Buat Account</div>
                                <div class="text-sm text-gray-600">Tambah akun baru (admin/accountant).</div>
                            </div>
                        </div>
                    </a>
                @endif

                <a class="menu-card group bg-white border rounded-lg p-4 hover:border-sky-200 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                    href="{{ route('finance.business_partners.index') }}"
                    data-keywords="business partner partner pelanggan supplier master data">
                    <div class="flex items-start gap-3">
                        <div class="shrink-0 rounded-md bg-sky-50 p-2 text-sky-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-900 group-hover:text-sky-700">Business Partners</div>
                            <div class="text-sm text-gray-600">Customer, supplier, doctor, dll.</div>
                        </div>
                    </div>
                </a>

                @if ($canWrite)
                    <a class="menu-card group bg-white border rounded-lg p-4 hover:border-sky-200 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                        href="{{ route('finance.business_partners.create') }}"
                        data-keywords="buat business partner create">
                        <div class="flex items-start gap-3">
                            <div class="shrink-0 rounded-md bg-sky-50 p-2 text-sky-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <div class="font-semibold text-gray-900 group-hover:text-sky-700">Buat Business Partner</div>
                                <div class="text-sm text-gray-600">Tambah partner baru (admin/accountant).</div>
                            </div>
                        </div>
                    </a>
                @endif
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const search = document.getElementById('menuSearch');
            const grid = document.getElementById('menuGrid');
            if (!search || !grid) return;

            const cards = Array.from(grid.querySelectorAll('.menu-card')).map(el => ({
                el,
                keywords: (el.getAttribute('data-keywords') || '').toLowerCase(),
                text: (el.textContent || '').toLowerCase(),
            }));

            function applyFilter(q) {
                const qq = (q || '').trim().toLowerCase();
                cards.forEach(c => {
                    const hit = !qq || c.keywords.includes(qq) || c.text.includes(qq);
                    c.el.style.display = hit ? '' : 'none';
                });
            }

            search.addEventListener('input', () => applyFilter(search.value));
            applyFilter('');
        });
    </script>
</x-app-layout>

@extends('finance.report-layout')

@php
    $qVal = (string) ($q ?? '');
@endphp

@section('title', 'Mapping Arus Kas (COA)')
@section('subtitle', 'Set cf_activity untuk akun (cash/operating/investing/financing)')

@section('header_actions')
    <a class="px-4 py-2 rounded border bg-white" href="{{ route('finance.accounts.index') }}">Back to COA</a>
    <a class="px-4 py-2 rounded border bg-white" href="{{ route('finance.cash_flow.index') }}">Arus Kas</a>
@endsection

@section('header_meta')
    <span class="report-chip">Total accounts: <span class="font-semibold">{{ count($accounts ?? []) }}</span></span>
@endsection

@section('tools')
    <style>
        .map-wrap {
            max-height: 70vh;
            overflow: auto;
        }

        .map-table thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #f9fafb;
        }
    </style>

    <div class="bg-white border rounded p-4 space-y-3">
        <div class="p-3 rounded border bg-yellow-50 text-yellow-900">
            <div class="font-semibold">Info</div>
            <div class="text-sm mt-1">
                Set kategori arus kas untuk akun lawan (counterpart). Minimal set <span class="font-semibold">cash</span>
                untuk akun kas/bank.
            </div>
        </div>

        <form method="GET" action="{{ route('finance.accounts.cashflow_mapping.index') }}" class="flex flex-wrap gap-2">
            <input name="q" value="{{ $qVal }}" placeholder="Search backend (optional)..."
                class="border rounded px-3 py-2 w-72" />
            <button class="px-4 py-2 rounded bg-gray-900 text-white">Reload</button>
            <a class="px-4 py-2 rounded border bg-white" href="{{ route('finance.accounts.cashflow_mapping.index') }}">Reset</a>
        </form>

        <div class="grid md:grid-cols-2 gap-3">
            <div>
                <label class="block text-sm mb-1">Cari di tabel (client-side)</label>
                <input id="tableSearch" type="text" class="border rounded px-3 py-2 w-full"
                    placeholder="ketik code / name / type..." autocomplete="off" />
            </div>

            <div class="flex items-end gap-2 flex-wrap">
                <button type="button" id="btnAutoCash"
                    class="px-4 py-2 rounded border bg-gray-50 border-gray-300">Auto set CASH</button>
                <button type="button" id="btnAutoInvesting"
                    class="px-4 py-2 rounded border bg-gray-50 border-gray-300">Auto set INVESTING</button>
                <button type="button" id="btnAutoFinancing"
                    class="px-4 py-2 rounded border bg-gray-50 border-gray-300">Auto set FINANCING</button>
            </div>
        </div>
    </div>
@endsection

@section('content')
    @if (!empty($apiError))
        <div class="p-3 rounded bg-yellow-100 text-yellow-800">
            {{ $apiError }}
        </div>
    @endif

    <form method="POST" action="{{ route('finance.accounts.cashflow_mapping.store') }}" class="space-y-3">
        @csrf

        <div class="bg-white border rounded">
            <div class="map-wrap">
                <table class="map-table w-full text-sm" id="mappingTable">
                    <thead>
                        <tr class="text-left border-b">
                            <th class="p-3 w-28">Code</th>
                            <th class="p-3">Name</th>
                            <th class="p-3 w-28">Type</th>
                            <th class="p-3 w-28">Postable</th>
                            <th class="p-3 w-64">Cash Flow Category</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y" id="mappingTbody">
                        @forelse (($accounts ?? []) as $a)
                            @php
                                $id = (string) data_get($a, 'id', '');
                                $code = (string) data_get($a, 'code', '');
                                $name = (string) data_get($a, 'name', '');
                                $type = (string) data_get($a, 'type', '');
                                $postable = (bool) data_get($a, 'is_postable', true);
                                $cf = (string) (old('cf_activity.' . $loop->index, data_get($a, 'cf_activity', '')) ?? '');

                                $rowText = strtolower(trim($code . ' ' . $name . ' ' . $type));
                            @endphp

                            <tr data-search="{{ e($rowText) }}" data-code="{{ e($code) }}" data-name="{{ e($name) }}"
                                data-type="{{ e($type) }}">
                                <td class="p-3 whitespace-nowrap">{{ $code }}</td>
                                <td class="p-3">{{ $name }}</td>
                                <td class="p-3">{{ $type }}</td>
                                <td class="p-3">
                                    @if ($postable)
                                        <span class="px-2 py-1 rounded bg-green-100 text-green-800 text-xs">Yes</span>
                                    @else
                                        <span class="px-2 py-1 rounded bg-gray-100 text-gray-800 text-xs">No</span>
                                    @endif
                                </td>
                                <td class="p-3">
                                    <input type="hidden" name="account_id[]" value="{{ $id }}">
                                    <select name="cf_activity[]" class="border rounded px-3 py-2 w-full cf-select">
                                        <option value="" {{ $cf === '' ? 'selected' : '' }}>— Default (Operating) —</option>
                                        <option value="cash" {{ $cf === 'cash' ? 'selected' : '' }}>cash</option>
                                        <option value="operating" {{ $cf === 'operating' ? 'selected' : '' }}>operating</option>
                                        <option value="investing" {{ $cf === 'investing' ? 'selected' : '' }}>investing</option>
                                        <option value="financing" {{ $cf === 'financing' ? 'selected' : '' }}>financing</option>
                                    </select>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-4 text-gray-500">No data</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="px-4 py-2 rounded bg-black text-white">Save Mapping</button>
            <a class="px-4 py-2 rounded border bg-white" href="{{ route('finance.accounts.index') }}">Cancel</a>
        </div>
    </form>
@endsection

@section('scripts')
    <script>
        (function() {
            const searchInput = document.getElementById('tableSearch');
            const tbody = document.getElementById('mappingTbody');

            function applyFilter() {
                const q = (searchInput?.value || '').trim().toLowerCase();
                const rows = tbody ? Array.from(tbody.querySelectorAll('tr[data-search]')) : [];

                rows.forEach(row => {
                    const text = row.getAttribute('data-search') || '';
                    row.style.display = q === '' || text.includes(q) ? '' : 'none';
                });
            }

            function setCategoryByRule(category, predicate) {
                const rows = tbody ? Array.from(tbody.querySelectorAll('tr[data-search]')) : [];
                rows.forEach(row => {
                    const code = (row.getAttribute('data-code') || '').toLowerCase();
                    const name = (row.getAttribute('data-name') || '').toLowerCase();
                    const type = (row.getAttribute('data-type') || '').toLowerCase();

                    if (!predicate({
                            code,
                            name,
                            type
                        })) return;

                    const select = row.querySelector('select.cf-select');
                    if (select) select.value = category;
                });
            }

            document.getElementById('btnAutoCash')?.addEventListener('click', () => {
                setCategoryByRule('cash', ({
                    code,
                    name
                }) => {
                    if (code.startsWith('11')) return true;
                    if (name.includes('kas') || name.includes('cash')) return true;
                    if (name.includes('bank') || name.includes('rekening')) return true;
                    return false;
                });
            });

            document.getElementById('btnAutoInvesting')?.addEventListener('click', () => {
                setCategoryByRule('investing', ({
                    code,
                    name,
                    type
                }) => {
                    if (code.startsWith('15') || code.startsWith('16')) return true;
                    if (type === 'asset' && (name.includes('aset tetap') || name.includes('fixed asset'))) return true;
                    return false;
                });
            });

            document.getElementById('btnAutoFinancing')?.addEventListener('click', () => {
                setCategoryByRule('financing', ({
                    code,
                    name,
                    type
                }) => {
                    if (code.startsWith('23') || code.startsWith('31')) return true;
                    if (type === 'liability' && (name.includes('utang bank') || name.includes('pinjaman'))) return true;
                    if (type === 'equity' && (name.includes('modal') || name.includes('equity'))) return true;
                    return false;
                });
            });

            searchInput?.addEventListener('input', applyFilter);
            applyFilter();
        })();
    </script>
@endsection


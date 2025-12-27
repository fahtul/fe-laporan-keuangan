@extends('finance.layout')
@section('title', 'Edit Account')

@section('content')
    <div class="bg-white border rounded p-4 max-w-xl">
        @if (!empty($parentsError))
            <div class="p-3 rounded bg-yellow-100 text-yellow-800 mb-3">
                {{ $parentsError }}
            </div>
        @endif

        <form method="POST" action="{{ route('finance.accounts.update', $account['id']) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm mb-1">Code</label>
                <input name="code" readonly class="border rounded w-full px-3 py-2 bg-gray-100 cursor-not-allowed"
                    value="{{ old('code', $account['code'] ?? '') }}" required>
            </div>

            <div>
                <label class="block text-sm mb-1">Name</label>
                <input name="name" value="{{ old('name', $account['name'] ?? '') }}"
                    class="border rounded w-full px-3 py-2" required>
            </div>

            <div>
                <label class="block text-sm mb-1">Cash Flow Category (optional)</label>
                @php $cfOld = old('cf_activity', $account['cf_activity'] ?? ''); @endphp
                <select name="cf_activity" class="border rounded p-2 w-full">
                    <option value="" {{ (string) $cfOld === '' ? 'selected' : '' }}>— Default (Operating) —</option>
                    <option value="cash" {{ (string) $cfOld === 'cash' ? 'selected' : '' }}>cash</option>
                    <option value="operating" {{ (string) $cfOld === 'operating' ? 'selected' : '' }}>operating</option>
                    <option value="investing" {{ (string) $cfOld === 'investing' ? 'selected' : '' }}>investing</option>
                    <option value="financing" {{ (string) $cfOld === 'financing' ? 'selected' : '' }}>financing</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Dipakai oleh report Arus Kas (Cash Flow).</p>
            </div>

            {{-- TYPE + NORMAL BALANCE (auto via JS) --}}
            <div>
                <label class="block text-sm mb-1">Type</label>
                @php $typeOld = old('type', $account['type'] ?? 'asset'); @endphp

                <input type="hidden" name="type" value="{{ $typeOld }}">

                <select id="typeSelect" class="border rounded p-2 w-full bg-gray-100 cursor-not-allowed" disabled>
                    <option value="asset" {{ $typeOld === 'asset' ? 'selected' : '' }}>asset</option>
                    <option value="liability" {{ $typeOld === 'liability' ? 'selected' : '' }}>liability</option>
                    <option value="equity" {{ $typeOld === 'equity' ? 'selected' : '' }}>equity</option>
                    <option value="revenue" {{ $typeOld === 'revenue' ? 'selected' : '' }}>revenue</option>
                    <option value="expense" {{ $typeOld === 'expense' ? 'selected' : '' }}>expense</option>
                </select>

                <label class="block text-sm mt-4 mb-1">Normal Balance (auto)</label>
                <input id="normalBalance" type="text" readonly class="border rounded p-2 w-full bg-gray-100"
                    value="" />
                <p class="text-xs text-gray-500 mt-1">Type tidak bisa diubah (COA).</p>
            </div>

            {{-- PARENT ACCOUNT --}}
            <div>
                <label class="block text-sm mb-1">Parent Account (optional)</label>
                <select id="parentSelect" name="parent_id" class="border rounded p-2 w-full">
                    <option value="">— No Parent —</option>
                    {{-- options injected by JS based on selected type --}}
                </select>
                <p class="text-xs text-gray-500 mt-1">
                    Parent biasanya akun header/grup (is_postable = false) dan harus satu type.
                </p>
            </div>

            {{-- IS_POSTABLE --}}
            <div class="border rounded p-3">
                <input type="hidden" name="is_postable" value="0">

                @php
                    $postableOld = old('is_postable', $account['is_postable'] ?? true ? 1 : 0);
                @endphp

                <input type="hidden" id="isPostableInput" name="is_postable" value="{{ (string) $postableOld }}">

                <div class="border rounded p-3 bg-gray-50 mb-3">
                    <label class="inline-flex items-center gap-2">
                        <input id="postableCheckbox" type="checkbox" class="rounded"
                            {{ (string) $postableOld === '1' ? 'checked' : '' }}>
                        <span class="text-sm font-medium">Postable (bisa dipakai transaksi)</span>
                    </label>

                    <p class="text-xs text-gray-500 mt-1">
                        Postable dikunci untuk menjaga konsistensi COA. (Nanti bisa dibuka jika ada aturan “belum ada
                        transaksi”.)
                    </p>
                </div>


                <div class="flex gap-2">
                    <button class="px-4 py-2 rounded bg-black text-white">Update</button>
                    <a class="px-4 py-2 rounded border" href="{{ route('finance.accounts.index') }}">Cancel</a>
                </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const typeSelect = document.getElementById('typeSelect'); // disabled UI, tapi value tetap ada
            const normalBalance = document.getElementById('normalBalance');
            const parentSelect = document.getElementById('parentSelect');

            const postableCheckbox = document.getElementById('postableCheckbox');
            const isPostableInput = document.getElementById('isPostableInput');

            const parents = @json($parents ?? []);
            const currentId = @json($account['id'] ?? '');
            const parentOld = @json(old('parent_id', $account['parent_id'] ?? ''));

            function calcNormalBalance(type) {
                return (type === 'asset' || type === 'expense') ? 'debit' : 'credit';
            }

            function normalizeBool(v, fallback = true) {
                if (v === null || v === undefined) return fallback;
                if (typeof v === 'boolean') return v;
                if (typeof v === 'number') return v === 1;
                if (typeof v === 'string') {
                    const s = v.trim().toLowerCase();
                    if (['1', 'true', 'yes', 'y'].includes(s)) return true;
                    if (['0', 'false', 'no', 'n'].includes(s)) return false;
                }
                return !!v;
            }

            function syncNormalBalance() {
                // ambil dari option selected
                const type = typeSelect.value;
                normalBalance.value = calcNormalBalance(type);
            }

            function renderParentOptions() {
                const currentType = typeSelect.value;

                parentSelect.innerHTML = '<option value="">— No Parent —</option>';

                const filtered = (parents || []).filter(p => {
                    if (currentId && p.id === currentId) return false;
                    const isPostable = normalizeBool(p.is_postable, true);
                    return p.type === currentType && isPostable === false; // parent = header
                });

                filtered.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = `${p.code} — ${p.name}`;
                    parentSelect.appendChild(opt);
                });

                if (parentOld) parentSelect.value = parentOld;

                if (parentSelect.value && !Array.from(parentSelect.options).some(o => o.value === parentSelect
                        .value)) {
                    parentSelect.value = '';
                }
            }

            // lock postable checkbox (tetap tampil tapi tidak bisa diubah manual)
            postableCheckbox.addEventListener('click', function(e) {
                e.preventDefault();
            });
            // keep hidden as source of truth (kalau future kamu mau set via JS)
            isPostableInput.value = postableCheckbox.checked ? "1" : "0";

            // init
            syncNormalBalance();
            renderParentOptions();
        });
    </script>
@endsection

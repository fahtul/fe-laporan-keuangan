@extends('finance.layout')
@section('title', 'Buat Opening Balance')
@section('subtitle', 'Neraca Awal / Saldo Awal (langsung POSTED)')

@section('content')
    <div class="bg-white border rounded p-4">
        @if (!empty($accountsError))
            <div class="p-3 rounded bg-yellow-100 text-yellow-800 mb-3">
                {{ $accountsError }}
            </div>
        @endif

        @if ($errors->any())
            <div class="p-3 rounded bg-red-100 text-red-800 mb-3">
                <div class="font-semibold mb-1">Perbaiki dulu:</div>
                <ul class="list-disc ml-5 text-sm">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- client-side errors --}}
        <div id="lineErrors" class="hidden p-3 rounded bg-red-100 text-red-800 mb-3">
            <div class="font-semibold mb-1">Perbaiki dulu:</div>
            <ul id="lineErrorsList" class="list-disc ml-5 text-sm"></ul>
        </div>

        <form method="POST" action="{{ route('finance.opening_balances.store') }}" id="openingForm" class="space-y-4">
            @csrf

            <div class="grid md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm mb-1">Opening Key (tahun)</label>
                    <input name="opening_key" value="{{ old('opening_key', $year) }}"
                        class="border rounded w-full px-3 py-2" required>
                    <p class="text-xs text-gray-500 mt-1">Contoh: 2026. Dibuat 1x per org + tahun.</p>
                </div>

                <div>
                    <label class="block text-sm mb-1">Tanggal</label>
                    <input type="date" name="date" value="{{ old('date', $defaultDate) }}"
                        class="border rounded w-full px-3 py-2" required>
                </div>

                <div>
                    <label class="block text-sm mb-1">Memo (optional)</label>
                    <input name="memo" value="{{ old('memo') }}" class="border rounded w-full px-3 py-2"
                        placeholder="Opening Balance Periode ...">
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button type="button" id="btnAddLine" class="px-3 py-2 rounded border">+ Tambah baris</button>

                <div class="ml-auto flex flex-wrap gap-3">
                    <div class="border rounded px-3 py-2">
                        <div class="text-xs text-gray-500">Total Debit</div>
                        <div class="font-semibold" id="totalDebit">0.00</div>
                    </div>
                    <div class="border rounded px-3 py-2">
                        <div class="text-xs text-gray-500">Total Credit</div>
                        <div class="font-semibold" id="totalCredit">0.00</div>
                    </div>
                    <div class="border rounded px-3 py-2" id="diffWrap">
                        <div class="text-xs text-gray-500">Selisih</div>
                        <div class="font-semibold" id="diff">0.00</div>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto border rounded">
                <table class="w-full text-sm" id="linesTable">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left p-3 min-w-[260px]">Account</th>
                            <th class="text-left p-3 min-w-[240px]">Business Partner</th>
                            <th class="text-right p-3 w-44">Debit</th>
                            <th class="text-right p-3 w-44">Credit</th>
                            <th class="text-left p-3 min-w-[220px]">Memo</th>
                            <th class="text-right p-3 w-16">#</th>
                        </tr>
                    </thead>
                    <tbody id="linesBody"></tbody>
                </table>
            </div>

            <div class="flex gap-2">
                <button class="px-4 py-2 rounded bg-black text-white" id="btnSubmit">
                    Create Opening Balance (POSTED)
                </button>
                <a class="px-4 py-2 rounded border" href="{{ route('finance.opening_balances.index', ['year' => $year]) }}">
                    Cancel
                </a>
            </div>

            <p class="text-xs text-gray-500">
                Tips: Isi salah satu sisi saja per baris (debit atau credit). Total debit harus sama dengan total credit.
            </p>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const accounts = @json($accounts ?? []);
            const bpOptionsUrl = @json(route('finance.business_partners.options'));

            const linesBody = document.getElementById('linesBody');
            const btnAddLine = document.getElementById('btnAddLine');
            const totalDebitEl = document.getElementById('totalDebit');
            const totalCreditEl = document.getElementById('totalCredit');
            const diffEl = document.getElementById('diff');
            const diffWrap = document.getElementById('diffWrap');
            const btnSubmit = document.getElementById('btnSubmit');

            const formEl = document.getElementById('openingForm');
            const errorsBox = document.getElementById('lineErrors');
            const errorsList = document.getElementById('lineErrorsList');

            function escapeHtml(s) {
                return String(s ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function fmt(n) {
                const x = Number(n || 0);
                return x.toFixed(2);
            }

            function parseNum(v) {
                if (v === null || v === undefined) return 0;
                const s = String(v).replace(/,/g, '').trim();
                const n = parseFloat(s);
                return isNaN(n) ? 0 : n;
            }

            function buildAccountOptions(selectedId = '') {
                const opts = ['<option value="">— pilih account —</option>'];
                (accounts || []).forEach(a => {
                    const id = a.id || '';
                    const label = `${a.code || ''} — ${a.name || ''}`;
                    const sel = (selectedId && selectedId === id) ? 'selected' : '';
                    const subledger = (a.subledger || '').toString().toLowerCase();
                    const requiresBp = (a.requires_bp ? '1' : '0');
                    opts.push(
                        `<option value="${escapeHtml(id)}" data-subledger="${escapeHtml(subledger)}" data-requires-bp="${requiresBp}" ${sel}>${escapeHtml(label)}</option>`
                    );
                });
                return opts.join('');
            }

            function getAccountMeta(tr) {
                const sel = tr.querySelector('.line-account');
                const opt = sel && sel.selectedOptions ? sel.selectedOptions[0] : null;
                const subledger = (opt?.dataset?.subledger || '').toLowerCase();
                const requiresBp = (opt?.dataset?.requiresBp || '') === '1';
                const text = opt?.textContent || '';

                return {
                    subledger,
                    requiresBp,
                    label: text,
                };
            }

            function getBpCategoriesForAccount(meta) {
                if (meta.subledger === 'ap') return ['supplier'];
                if (meta.subledger === 'ar') return ['customer', 'insurer'];
                if (meta.requiresBp) return [];
                return null;
            }

            const bpCache = {
                customer: null,
                supplier: null,
                insurer: null,
                ar: null,
                all: null,
            };

            async function fetchBpOptions(category, q = '') {
                const u = new URL(bpOptionsUrl, window.location.origin);
                if (category) u.searchParams.set('category', category);
                if (q) u.searchParams.set('q', q);
                u.searchParams.set('limit', '100');

                const res = await fetch(u.toString(), {
                    headers: {
                        'Accept': 'application/json',
                    }
                });

                const json = await res.json().catch(() => ({}));
                if (!json || json.success !== true) return [];

                const items = Array.isArray(json.data) ? json.data : [];
                return items.filter(it => (it.is_active ?? true) === true);
            }

            async function getBpOptionsForCategories(categories) {
                if (!categories) return [];

                if (categories.length === 0) {
                    if (bpCache.all) return bpCache.all;
                    const items = await fetchBpOptions('', '');
                    bpCache.all = items;
                    return items;
                }

                if (categories.length === 1) {
                    const cat = categories[0];
                    if (bpCache[cat]) return bpCache[cat];
                    const items = await fetchBpOptions(cat, '');
                    bpCache[cat] = items;
                    return items;
                }

                // AR: customer + insurer (merge unique)
                if (bpCache.ar) return bpCache.ar;
                const results = await Promise.all(categories.map(cat => fetchBpOptions(cat, '')));
                const merged = [];
                const seen = new Set();
                results.flat().forEach(it => {
                    const id = String(it.id ?? '');
                    if (!id || seen.has(id)) return;
                    seen.add(id);
                    merged.push(it);
                });
                bpCache.ar = merged;
                return merged;
            }

            function renderBpSelectOptions(selectEl, items, placeholder) {
                const opts = [`<option value="">${escapeHtml(placeholder || '— pilih BP —')}</option>`];
                (items || []).forEach(it => {
                    const id = String(it.id ?? '');
                    if (!id) return;
                    const code = String(it.code ?? '');
                    const name = String(it.name ?? '');
                    const label = `${code} — ${name}`.trim();
                    opts.push(`<option value="${escapeHtml(id)}">${escapeHtml(label)}</option>`);
                });
                selectEl.innerHTML = opts.join('');
            }

            async function refreshBpUI(tr) {
                const meta = getAccountMeta(tr);
                const categories = getBpCategoriesForAccount(meta);

                const bpSelect = tr.querySelector('.line-bp-select');
                const bpHidden = tr.querySelector('[name="line_bp_id[]"]');
                const bpHint = tr.querySelector('.bp-hint');
                const accountId = (tr.querySelector('.line-account')?.value || '').trim();

                if (!bpSelect || !bpHidden) return;

                // no account selected yet
                if (!accountId) {
                    bpSelect.disabled = true;
                    bpSelect.required = false;
                    renderBpSelectOptions(bpSelect, [], 'Pilih akun AR/AP dulu');
                    bpSelect.value = '';
                    bpHidden.value = '';
                    if (bpHint) bpHint.textContent = '';
                    return;
                }

                // not required
                if (!categories) {
                    bpSelect.disabled = true;
                    bpSelect.required = false;
                    renderBpSelectOptions(bpSelect, [], '(Tidak perlu BP)');
                    bpSelect.value = '';
                    bpHidden.value = '';
                    if (bpHint) bpHint.textContent = '';
                    return;
                }

                // required / enabled
                bpSelect.disabled = false;
                bpSelect.required = true;

                renderBpSelectOptions(bpSelect, [], 'Loading BP...');
                const items = await getBpOptionsForCategories(categories);
                renderBpSelectOptions(bpSelect, items, 'Pilih Business Partner');

                // keep existing selection if possible, else clear
                const current = (bpHidden.value || '').trim();
                if (current) {
                    const exists = items.some(it => String(it.id ?? '') === current);
                    if (exists) {
                        bpSelect.value = current;
                    } else {
                        bpSelect.value = '';
                        bpHidden.value = '';
                    }
                }

                if (bpHint) {
                    const hint =
                        meta.subledger === 'ar' ? 'AR: customer/insurer' :
                        meta.subledger === 'ap' ? 'AP: supplier' :
                        'Wajib BP';
                    bpHint.textContent = hint;
                }
            }

            function addLine(prefill = {}) {
                const tr = document.createElement('tr');
                tr.className = 'border-t';

                tr.innerHTML = `
                    <td class="p-3">
                        <select name="line_account_id[]" class="border rounded px-2 py-2 w-full line-account">
                            ${buildAccountOptions(prefill.account_id || '')}
                        </select>
                    </td>

                    <td class="p-3">
                        <select class="border rounded px-2 py-2 w-full line-bp-select" disabled>
                            <option value="">Pilih akun AR/AP dulu</option>
                        </select>
                        <input type="hidden" name="line_bp_id[]" value="${escapeHtml(prefill.bp_id || '')}">
                        <div class="text-xs text-gray-500 mt-1 bp-hint"></div>
                    </td>

                    <td class="p-3 text-right">
                        <input name="line_debit[]" type="number" step="0.01" min="0"
                               class="border rounded px-2 py-2 w-full text-right line-debit"
                               value="${prefill.debit ?? ''}" placeholder="0.00">
                    </td>

                    <td class="p-3 text-right">
                        <input name="line_credit[]" type="number" step="0.01" min="0"
                               class="border rounded px-2 py-2 w-full text-right line-credit"
                               value="${prefill.credit ?? ''}" placeholder="0.00">
                    </td>

                    <td class="p-3">
                        <input name="line_memo[]" class="border rounded px-2 py-2 w-full line-memo"
                               value="${prefill.memo ?? ''}" placeholder="optional">
                    </td>

                    <td class="p-3 text-right">
                        <button type="button" class="text-red-600 underline btn-remove">Remove</button>
                    </td>
                `;

                linesBody.appendChild(tr);
                wireRow(tr);
                refreshBpUI(tr);
                recalc();
            }

            function wireRow(tr) {
                const account = tr.querySelector('.line-account');
                const bpSelect = tr.querySelector('.line-bp-select');
                const bpHidden = tr.querySelector('[name="line_bp_id[]"]');

                const debit = tr.querySelector('.line-debit');
                const credit = tr.querySelector('.line-credit');
                const removeBtn = tr.querySelector('.btn-remove');

                function onDebitChange() {
                    const d = parseNum(debit.value);
                    if (d > 0) credit.value = ''; // enforce salah satu sisi
                    recalc();
                }

                function onCreditChange() {
                    const c = parseNum(credit.value);
                    if (c > 0) debit.value = '';
                    recalc();
                }

                debit.addEventListener('input', onDebitChange);
                credit.addEventListener('input', onCreditChange);

                account?.addEventListener('change', () => {
                    // reset BP selection when account changes
                    if (bpHidden) bpHidden.value = '';
                    if (bpSelect) bpSelect.value = '';
                    refreshBpUI(tr);
                });

                bpSelect?.addEventListener('change', () => {
                    if (!bpHidden) return;
                    bpHidden.value = (bpSelect.value || '').trim();
                });

                removeBtn.addEventListener('click', () => {
                    tr.remove();
                    recalc();
                });
            }

            function recalc() {
                const rows = Array.from(linesBody.querySelectorAll('tr'));
                let td = 0,
                    tc = 0;

                rows.forEach(tr => {
                    const debit = parseNum(tr.querySelector('.line-debit')?.value);
                    const credit = parseNum(tr.querySelector('.line-credit')?.value);
                    td += debit;
                    tc += credit;
                });

                const diff = td - tc;

                totalDebitEl.textContent = fmt(td);
                totalCreditEl.textContent = fmt(tc);
                diffEl.textContent = fmt(diff);

                const ok = Math.abs(diff) < 0.005 && rows.length >= 2;
                diffWrap.classList.toggle('bg-green-50', ok);
                diffWrap.classList.toggle('bg-red-50', !ok);

                // optional: disable submit kalau belum balance
                btnSubmit.disabled = !ok;
                btnSubmit.classList.toggle('opacity-60', !ok);
                btnSubmit.classList.toggle('cursor-not-allowed', !ok);
            }

            btnAddLine.addEventListener('click', () => addLine());

            function renderClientErrors(errors) {
                if (!errorsBox || !errorsList) return;
                if (!errors || errors.length === 0) {
                    errorsBox.classList.add('hidden');
                    errorsList.innerHTML = '';
                    return;
                }
                errorsBox.classList.remove('hidden');
                errorsList.innerHTML = errors.map(e => `<li>${escapeHtml(e)}</li>`).join('');
            }

            formEl?.addEventListener('submit', (e) => {
                const rows = Array.from(linesBody.querySelectorAll('tr'));
                const errors = [];
                let firstErrorEl = null;

                rows.forEach((tr, idx) => {
                    const meta = getAccountMeta(tr);
                    const categories = getBpCategoriesForAccount(meta);
                    if (!categories) return;

                    const bpId = (tr.querySelector('[name="line_bp_id[]"]')?.value || '').trim();
                    if (bpId) return;

                    const suffix = meta.subledger ? ` (${meta.subledger.toUpperCase()})` : '';
                    errors.push(`Baris #${idx + 1}: akun ${meta.label}${suffix} wajib pilih Business Partner`);

                    if (!firstErrorEl) {
                        firstErrorEl = tr.querySelector('.line-bp-select') || tr.querySelector('.line-account');
                    }
                });

                if (errors.length) {
                    e.preventDefault();
                    renderClientErrors(errors);
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                    setTimeout(() => firstErrorEl?.focus?.(), 100);
                } else {
                    renderClientErrors([]);
                }
            });

            // old() restore
            const oldIds = @json(old('line_account_id', []));
            const oldDeb = @json(old('line_debit', []));
            const oldCre = @json(old('line_credit', []));
            const oldMem = @json(old('line_memo', []));
            const oldBp = @json(old('line_bp_id', []));

            if (oldIds && oldIds.length) {
                for (let i = 0; i < oldIds.length; i++) {
                    addLine({
                        account_id: oldIds[i] || '',
                        bp_id: oldBp[i] || '',
                        debit: oldDeb[i] || '',
                        credit: oldCre[i] || '',
                        memo: oldMem[i] || '',
                    });
                }
            } else {
                // init: minimal 2 baris
                addLine();
                addLine();
            }
        });
    </script>
@endsection

@extends('finance.layout')
@section('title', 'Create Journal Entry')
@section('subtitle', 'Draft journal (debit/credit via lines)')

@section('content')
    <div class="bg-white border rounded p-4">
        @if (!empty($accountsError))
            <div class="p-3 rounded bg-yellow-100 text-yellow-800 mb-3">
                {{ $accountsError }}
            </div>
        @endif

        {{-- server-side errors --}}
        @if ($errors->any())
            <div class="p-3 rounded bg-red-100 text-red-800 mb-3">
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

        <form method="POST" action="{{ route('finance.journal_entries.store') }}" class="space-y-4" id="entryForm">
            @csrf

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm mb-1">Date</label>
                    <input type="date" name="date" value="{{ old('date', now()->toDateString()) }}"
                        class="border rounded w-full px-3 py-2" required>
                </div>

                <div>
                    <label class="block text-sm mb-1">Memo</label>
                    <input name="memo" value="{{ old('memo') }}" class="border rounded w-full px-3 py-2"
                        placeholder="Optional memo...">
                </div>
            </div>

            <div class="border rounded">
                <div class="flex items-center justify-between p-3 border-b bg-gray-50">
                    <div class="font-semibold">Lines</div>
                    <button type="button" id="addLineBtn" class="px-3 py-1.5 rounded bg-black text-white text-sm">
                        + Add line
                    </button>
                </div>

                <div class="overflow-x-auto">
	                    <table class="w-full text-sm" id="linesTable">
	                        <thead class="bg-gray-50">
	                            <tr>
	                                <th class="text-left p-3">Account</th>
	                                <th class="text-left p-3">BP</th>
	                                <th class="text-left p-3">Memo</th>
	                                <th class="text-right p-3">Debit</th>
	                                <th class="text-right p-3">Credit</th>
	                                <th class="text-right p-3">#</th>
	                            </tr>
                        </thead>
                        <tbody id="linesBody"></tbody>
	                        <tfoot>
	                            <tr class="border-t bg-gray-50">
	                                <td class="p-3 font-semibold" colspan="3">Totals</td>
	                                <td class="p-3 text-right font-semibold" id="totalDebit">0.00</td>
	                                <td class="p-3 text-right font-semibold" id="totalCredit">0.00</td>
	                                <td class="p-3 text-right">
	                                    <span id="balanceBadge"
                                        class="inline-flex px-2 py-1 rounded text-xs bg-gray-100 text-gray-800">
                                        Not balanced
                                    </span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="p-3 text-xs text-gray-500">
                    Rules: tiap baris hanya boleh isi <b>debit</b> atau <b>credit</b> (bukan dua-duanya). Minimal 2 baris.
                    Balance artinya total debit = total credit &gt; 0.
                </div>
            </div>

            <div class="flex flex-wrap gap-2 items-center">
                <button type="submit" id="saveBtn" name="submit_action" value="draft"
                    class="px-4 py-2 rounded bg-black text-white">
                    Save Draft
                </button>

                {{-- tombol post: aktif hanya kalau valid + balanced --}}
                <button type="submit" id="postBtn" name="submit_action" value="post"
                    class="px-4 py-2 rounded text-white bg-green-700 opacity-60 cursor-not-allowed"
                    style="background:#15803d" disabled>
                    Post Journal Entry
                </button>

                <a class="px-4 py-2 rounded border" href="{{ route('finance.journal_entries.index') }}">Cancel</a>

                <div class="w-full text-xs text-gray-500">
                    Tombol Post aktif jika lines valid dan total debit = total credit &gt; 0.
                </div>
            </div>
        </form>
    </div>

	    <template id="accountOptionsTemplate">
	        <option value="">— Select account —</option>
	        @foreach ($accounts as $a)
	            @php $isPostable = (bool)($a['is_postable'] ?? true); @endphp
	            @if ($isPostable)
	                <option value="{{ $a['id'] }}" data-subledger="{{ e((string) ($a['subledger'] ?? '')) }}"
	                    data-requires-bp="{{ !empty($a['requires_bp']) ? '1' : '0' }}">
	                    {{ $a['code'] ?? '' }} — {{ $a['name'] ?? '' }}
	                </option>
	            @endif
	        @endforeach
	    </template>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('entryForm');
            const saveBtn = document.getElementById('saveBtn');
            const postBtn = document.getElementById('postBtn');

	            const linesBody = document.getElementById('linesBody');
	            const addBtn = document.getElementById('addLineBtn');
	            const optTpl = document.getElementById('accountOptionsTemplate').innerHTML;
	            const bpOptionsUrl = @json(route('finance.business_partners.options'));

            const totalDebitEl = document.getElementById('totalDebit');
            const totalCreditEl = document.getElementById('totalCredit');
            const badgeEl = document.getElementById('balanceBadge');

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

            function money(n) {
                const x = Number(n || 0);
                return x.toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

	            function getRows() {
	                return Array.from(linesBody.querySelectorAll('tr'));
	            }

	            function getAccountMeta(tr) {
	                const sel = tr.querySelector('.accountSelect');
	                const opt = sel && sel.selectedOptions ? sel.selectedOptions[0] : null;
	                const subledger = (opt?.dataset?.subledger || '').toLowerCase();
	                const requiresBp = (opt?.dataset?.requiresBp || '') === '1';

	                return {
	                    subledger,
	                    requiresBp,
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
	                // ensure active only (kalau BE masih kirim inactive)
	                return items.filter(it => (it.is_active ?? true) === true);
	            }

	            async function getBpOptionsForCategories(categories, q = '') {
	                if (!categories) return [];

	                if (categories.length === 0) {
	                    return await fetchBpOptions('', q);
	                }

	                if (categories.length === 1) {
	                    const cat = categories[0];
	                    if (!q && bpCache[cat]) return bpCache[cat];
	                    const items = await fetchBpOptions(cat, q);
	                    if (!q) bpCache[cat] = items;
	                    return items;
	                }

	                const results = await Promise.all(categories.map(cat => fetchBpOptions(cat, q)));
	                const merged = [];
	                const seen = new Set();
	                results.flat().forEach(it => {
	                    const id = String(it.id ?? '');
	                    if (!id || seen.has(id)) return;
	                    seen.add(id);
	                    merged.push(it);
	                });
	                return merged;
	            }

	            function renderBpSelectOptions(selectEl, items) {
	                const opts = ['<option value="">— Select BP —</option>'];
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

	            async function refreshBpUI(tr, q = '') {
	                const meta = getAccountMeta(tr);
	                const categories = getBpCategoriesForAccount(meta);

	                const bpBox = tr.querySelector('.bpBox');
	                const bpSelect = tr.querySelector('.bpSelect');
	                const bpHidden = tr.querySelector('[name="line_bp_id[]"]');
	                const bpHint = tr.querySelector('.bpHint');
	                const bpNotRequired = tr.querySelector('.bpNotRequired');

	                if (!bpBox || !bpSelect || !bpHidden) return;

	                if (!categories) {
	                    bpBox.classList.add('hidden');
	                    if (bpNotRequired) bpNotRequired.classList.remove('hidden');
	                    bpHidden.value = '';
	                    bpSelect.innerHTML = '<option value="">— Select BP —</option>';
	                    return;
	                }

	                bpBox.classList.remove('hidden');
	                if (bpNotRequired) bpNotRequired.classList.add('hidden');

	                const hintParts = [];
	                if (meta.subledger) hintParts.push(`subledger: ${meta.subledger.toUpperCase()}`);
	                if (categories.length === 1) hintParts.push(`filter: ${categories[0]}`);
	                if (categories.length > 1) hintParts.push(`filter: ${categories.join(' / ')}`);
	                if (categories.length === 0) hintParts.push('filter: all');
	                if (bpHint) bpHint.textContent = hintParts.join(' • ');

	                const current = bpHidden.value || '';
	                const items = await getBpOptionsForCategories(categories, q);
	                renderBpSelectOptions(bpSelect, items);
	                bpSelect.value = current;
	            }

	            function computeTotals() {
	                let td = 0,
	                    tc = 0;

	                getRows().forEach(tr => {
	                    const debit = Number(tr.querySelector('[name="line_debit[]"]').value || 0);
	                    const credit = Number(tr.querySelector('[name="line_credit[]"]').value || 0);
	                    td += debit;
	                    tc += credit;
	                });

                const balanced = Math.round(td * 100) === Math.round(tc * 100) && td > 0;
                return {
                    td,
                    tc,
                    balanced
                };
            }

	            function validateRows() {
	                const rows = getRows();
	                const errs = [];

	                if (rows.length < 2) errs.push('Minimal 2 lines.');

	                rows.forEach((tr, idx) => {
	                    const i = idx + 1;
	                    const accountId = (tr.querySelector('[name="line_account_id[]"]')?.value || '').trim();
	                    const meta = getAccountMeta(tr);
	                    const needsBp = getBpCategoriesForAccount(meta) !== null;
	                    const bpId = (tr.querySelector('[name="line_bp_id[]"]')?.value || '').trim();
	                    const debit = Number(tr.querySelector('[name="line_debit[]"]')?.value || 0);
	                    const credit = Number(tr.querySelector('[name="line_credit[]"]')?.value || 0);

	                    if (!accountId) errs.push(`Line #${i}: account wajib dipilih.`);
	                    if (accountId && needsBp && !bpId) errs.push(`Line #${i}: BP wajib dipilih untuk account ini.`);
	                    if (debit < 0 || credit < 0) errs.push(`Line #${i}: debit/credit tidak boleh negatif.`);
	                    if (debit > 0 && credit > 0) errs.push(
	                        `Line #${i}: tidak boleh isi debit dan credit sekaligus.`);
	                    if (debit === 0 && credit === 0) errs.push(`Line #${i}: debit atau credit harus > 0.`);
	                });

                return errs;
            }

            function renderErrors(errs) {
                if (!errs.length) {
                    errorsBox.classList.add('hidden');
                    errorsList.innerHTML = '';
                    return;
                }
                errorsBox.classList.remove('hidden');
                errorsList.innerHTML = errs.map(e => `<li>${escapeHtml(e)}</li>`).join('');
            }

            function setBtnDisabled(btn, disabled) {
                if (!btn) return;
                btn.disabled = !!disabled;
                btn.classList.toggle('opacity-60', btn.disabled);
                btn.classList.toggle('cursor-not-allowed', btn.disabled);
            }

            function recalc() {
                const {
                    td,
                    tc,
                    balanced
                } = computeTotals();

                totalDebitEl.textContent = money(td);
                totalCreditEl.textContent = money(tc);

                badgeEl.textContent = balanced ? 'Balanced' : 'Not balanced';
                badgeEl.className = 'inline-flex px-2 py-1 rounded text-xs ' + (balanced ?
                    'bg-green-100 text-green-800' :
                    'bg-gray-100 text-gray-800');

                const errs = validateRows();
                renderErrors(errs);

                // draft: cukup valid
                setBtnDisabled(saveBtn, errs.length > 0);

                // post: valid + balanced
                setBtnDisabled(postBtn, errs.length > 0 || !balanced);
            }

	            function bindRow(tr) {
	                const accountSel = tr.querySelector('.accountSelect');
	                const debit = tr.querySelector('[name="line_debit[]"]');
	                const credit = tr.querySelector('[name="line_credit[]"]');
	                const removeBtn = tr.querySelector('[data-remove]');
	                const bpSelect = tr.querySelector('.bpSelect');
	                const bpHidden = tr.querySelector('[name="line_bp_id[]"]');
	                const bpSearch = tr.querySelector('.bpSearch');
	                let bpTimer = null;

	                debit.addEventListener('input', () => {
	                    if (Number(debit.value || 0) > 0) credit.value = 0;
	                    recalc();
	                });
	                credit.addEventListener('input', () => {
	                    if (Number(credit.value || 0) > 0) debit.value = 0;
	                    recalc();
	                });

	                if (accountSel) {
	                    accountSel.addEventListener('change', () => {
	                        // kalau account berubah, reset bp value (biar tidak nyangkut ke account lain)
	                        if (bpHidden) bpHidden.value = '';
	                        if (bpSelect) bpSelect.value = '';
	                        refreshBpUI(tr, '').then(recalc);
	                    });
	                }

	                if (bpSelect && bpHidden) {
	                    bpSelect.addEventListener('change', () => {
	                        bpHidden.value = bpSelect.value || '';
	                        recalc();
	                    });
	                }

	                if (bpSearch) {
	                    bpSearch.addEventListener('input', () => {
	                        clearTimeout(bpTimer);
	                        bpTimer = setTimeout(() => {
	                            refreshBpUI(tr, (bpSearch.value || '').trim());
	                        }, 300);
	                    });
	                }

	                tr.querySelectorAll('select,input').forEach(el => {
	                    el.addEventListener('change', recalc);
	                    el.addEventListener('input', recalc);
	                });

	                removeBtn.addEventListener('click', () => {
	                    tr.remove();
	                    recalc();
	                });
	            }

	            function addLineRow(prefill = {}) {
	                const tr = document.createElement('tr');
	                tr.className = 'border-t';
	                tr.innerHTML = `
	                    <td class="p-3">
	                        <select name="line_account_id[]" class="border rounded p-2 w-full accountSelect" required>
	                            ${optTpl}
	                        </select>
	                    </td>
	                    <td class="p-3">
	                        <div class="bpBox hidden">
	                            <select class="border rounded p-2 w-full bpSelect">
	                                <option value="">— Select BP —</option>
	                            </select>
	                            <input type="hidden" name="line_bp_id[]" value="${escapeHtml(prefill.bp_id || '')}">
	                            <input type="text" class="bpSearch border rounded px-3 py-2 w-full mt-2 text-sm"
	                                placeholder="Cari BP (optional)..." value="">
	                            <div class="bpHint text-xs text-gray-500 mt-1"></div>
	                        </div>
	                        <div class="bpNotRequired text-gray-400">-</div>
	                    </td>
	                    <td class="p-3">
	                        <input name="line_memo[]" class="border rounded px-3 py-2 w-full" placeholder="Line memo..."
	                            value="${escapeHtml(prefill.memo || '')}">
	                    </td>
	                    <td class="p-3 text-right">
                        <input type="number" step="0.01" min="0" name="line_debit[]" class="border rounded px-3 py-2 w-32 text-right"
                            value="${Number(prefill.debit ?? 0)}">
                    </td>
                    <td class="p-3 text-right">
                        <input type="number" step="0.01" min="0" name="line_credit[]" class="border rounded px-3 py-2 w-32 text-right"
                            value="${Number(prefill.credit ?? 0)}">
                    </td>
                    <td class="p-3 text-right">
                        <button type="button" data-remove class="underline text-red-600">Remove</button>
                    </td>
	                `;
	                linesBody.appendChild(tr);

	                if (prefill.account_id) tr.querySelector('.accountSelect').value = prefill.account_id;
	                // sync hidden -> select for BP
	                const bpHidden = tr.querySelector('[name="line_bp_id[]"]');
	                const bpSelect = tr.querySelector('.bpSelect');
	                if (bpHidden && bpSelect) bpSelect.value = bpHidden.value || '';

	                bindRow(tr);
	                refreshBpUI(tr, '');
	                recalc();
	            }

            addBtn.addEventListener('click', () => addLineRow());

            // old() restore
	            const oldIds = @json(old('line_account_id', []));
	            const oldDeb = @json(old('line_debit', []));
	            const oldCre = @json(old('line_credit', []));
	            const oldMem = @json(old('line_memo', []));
	            const oldBp = @json(old('line_bp_id', []));

            if (oldIds && oldIds.length) {
	                for (let i = 0; i < oldIds.length; i++) {
	                    addLineRow({
	                        account_id: oldIds[i] || '',
	                        bp_id: oldBp[i] || '',
	                        debit: Number(oldDeb[i] || 0),
	                        credit: Number(oldCre[i] || 0),
	                        memo: oldMem[i] || '',
	                    });
	                }
	            } else {
                addLineRow({});
                addLineRow({});
            }

            // extra guard: cegah post kalau tidak balanced
            form.addEventListener('submit', function(e) {
                const errs = validateRows();
                const {
                    balanced
                } = computeTotals();

                const submitter = e.submitter || document.activeElement;
                const isPost = submitter && submitter.id === 'postBtn';

                if (errs.length) {
                    renderErrors(errs);
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }

                if (isPost && !balanced) {
                    renderErrors(['Tidak bisa POST: total debit harus sama dengan total credit dan > 0.']);
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
            });

            // init
            recalc();
        });
    </script>
@endsection

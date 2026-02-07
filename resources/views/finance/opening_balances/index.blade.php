@extends('finance.layout')

@section('title', 'Neraca Awal / Saldo Awal')
@section('subtitle', 'Opening Balance (jurnal pembuka)')

@section('header_actions')
    @if (in_array(auth()->user()->role, ['admin', 'accountant']))
        @if (!$opening)
            <a href="{{ route('finance.opening_balances.create', ['year' => $year]) }}"
                class="px-4 py-2 rounded bg-black text-white">
                + Buat Opening Balance
            </a>
        @endif
    @endif
@endsection

@section('content')
    <div class="bg-white border rounded p-4">
        <form class="flex gap-2 mb-4 items-end" method="GET" action="{{ route('finance.opening_balances.index') }}">
            <div>
                <label class="block text-xs mb-1 text-gray-600">Tahun / opening_key</label>
                <input name="year" value="{{ $year }}" class="border rounded px-3 py-2 w-48" placeholder="2026" />
            </div>

            <button class="px-4 py-2 rounded bg-gray-900 text-white">Lihat</button>

            @if (in_array(auth()->user()->role, ['admin', 'accountant']) && !$opening)
                <a class="px-4 py-2 rounded border"
                    href="{{ route('finance.opening_balances.create', ['year' => $year]) }}">
                    Buat
                </a>
            @endif
        </form>

        @if (!empty($accountsError) && in_array(auth()->user()->role, ['admin', 'accountant']))
            <div class="p-3 rounded bg-yellow-100 text-yellow-800 mb-3">
                {{ $accountsError }}
            </div>
        @endif

        @if ($apiError)
            <div class="p-3 rounded bg-red-100 text-red-800 mb-3">{{ $apiError }}</div>
        @endif
        @if ($errors->any())
            <div class="p-3 rounded bg-red-100 text-red-800 mb-3">
                {{ $errors->first('api') ?? $errors->first() }}
            </div>
        @endif

        @if (!$opening)
            <div class="p-3 rounded bg-gray-50 text-gray-700 border">
                Belum ada Opening Balance untuk <span class="font-semibold">{{ $year }}</span>.
            </div>
        @else
            @php
                $lines = $opening['lines'] ?? [];
                $totalDebit = collect($lines)->sum(fn($l) => (float) ($l['debit'] ?? 0));
                $totalCredit = collect($lines)->sum(fn($l) => (float) ($l['credit'] ?? 0));
                $diff = $totalDebit - $totalCredit;
            @endphp

            <div class="flex flex-wrap gap-3 mb-4">
                <div class="border rounded p-3 bg-gray-50">
                    <div class="text-xs text-gray-500">Tanggal</div>
                    <div class="font-semibold">{{ $opening['date'] ?? '-' }}</div>
                </div>
                <div class="border rounded p-3 bg-gray-50">
                    <div class="text-xs text-gray-500">Status</div>
                    <div class="font-semibold">
                        <span class="px-2 py-1 rounded bg-green-100 text-green-800 text-xs">POSTED</span>
                    </div>
                </div>
                <div class="border rounded p-3">
                    <div class="text-xs text-gray-500">Total Debit</div>
                    <div class="font-semibold">{{ number_format($totalDebit, 2, ',', '.') }}</div>
                </div>
                <div class="border rounded p-3">
                    <div class="text-xs text-gray-500">Total Kredit</div>
                    <div class="font-semibold">{{ number_format($totalCredit, 2, ',', '.') }}</div>
                </div>
                <div class="border rounded p-3 {{ abs($diff) < 0.005 ? 'bg-green-50' : 'bg-red-50' }}">
                    <div class="text-xs text-gray-500">Selisih</div>
                    <div class="font-semibold">{{ number_format($diff, 2, ',', '.') }}</div>
                </div>
            </div>

            @if (!empty($opening['memo']))
                <div class="mb-4 p-3 rounded bg-gray-50 border">
                    <div class="text-xs text-gray-500 mb-1">Memo</div>
                    <div class="text-sm">{{ $opening['memo'] }}</div>
                </div>
            @endif

            @if (in_array(auth()->user()->role, ['admin', 'accountant']))
                {{-- client-side errors --}}
                <div id="editLineErrors" class="hidden p-3 rounded bg-red-100 text-red-800 mb-3">
                    <div class="font-semibold mb-1">Perbaiki dulu:</div>
                    <ul id="editLineErrorsList" class="list-disc ml-5 text-sm"></ul>
                </div>

                <div class="mb-4 p-4 rounded border bg-white">
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <div>
                            <div class="font-semibold text-gray-900">Edit Opening Balance</div>
                            <div class="text-sm text-gray-600">Remove untuk hapus baris, lalu Save. Baris kosong/0-0 boleh ada.</div>
                        </div>
                        <button type="button" id="btnAddEditLine"
                            class="px-3 py-2 rounded border bg-white hover:bg-gray-50">
                            + Tambah baris
                        </button>
                    </div>

                    <form method="POST"
                        action="{{ route('finance.opening_balances.update', ['id' => $opening['id'], 'year' => $year]) }}"
                        id="openingEditForm" class="space-y-4 mt-4">
                        @csrf
                        @method('PUT')

                        <div class="grid md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm mb-1">Tanggal</label>
                                <input type="date" name="date"
                                    value="{{ old('date', $opening['date'] ?? ($year . '-01-01')) }}"
                                    class="border rounded w-full px-3 py-2" required>
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-sm mb-1">Memo (optional)</label>
                                <input name="memo" value="{{ old('memo', $opening['memo'] ?? '') }}"
                                    class="border rounded w-full px-3 py-2" placeholder="Opening Balance ...">
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <div class="ml-auto flex flex-wrap gap-3">
                                <div class="border rounded px-3 py-2">
                                    <div class="text-xs text-gray-500">Total Debit</div>
                                    <div class="font-semibold" id="editTotalDebit">0.00</div>
                                </div>
                                <div class="border rounded px-3 py-2">
                                    <div class="text-xs text-gray-500">Total Credit</div>
                                    <div class="font-semibold" id="editTotalCredit">0.00</div>
                                </div>
                                <div class="border rounded px-3 py-2" id="editDiffWrap">
                                    <div class="text-xs text-gray-500">Selisih</div>
                                    <div class="font-semibold" id="editDiff">0.00</div>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto border rounded">
                            <table class="w-full text-sm">
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
                                <tbody id="editLinesBody"></tbody>
                            </table>
                        </div>

                        <div class="flex gap-2">
                            <button class="px-4 py-2 rounded bg-black text-white" id="btnEditSubmit">
                                Save
                            </button>
                            <a class="px-4 py-2 rounded border"
                                href="{{ route('finance.opening_balances.index', ['year' => $year]) }}">
                                Reset
                            </a>
                        </div>

                        <p class="text-xs text-gray-500">
                            Aturan: baris non-zero wajib isi salah satu sisi (debit XOR credit). Total debit == total credit, total &gt; 0, minimal 2 baris non-zero.
                        </p>
                    </form>
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left p-3">Account</th>
                            <th class="text-right p-3">Debit</th>
                            <th class="text-right p-3">Credit</th>
                            <th class="text-left p-3">Memo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($lines as $l)
                            <tr class="border-t">
                                <td class="p-3">
                                    @php
                                        $accId = $l['account_id'] ?? null;
                                        $acc = $accId ? ($accountsById[$accId] ?? null) : null;
                                        $accLabel = $acc ? trim(($acc['code'] ?? '') . ' — ' . ($acc['name'] ?? '')) : null;
                                    @endphp
                                    <div class="font-medium">{{ $accLabel ?: ($accId ?? '-') }}</div>
                                    @if ($accId)
                                        <div class="text-xs text-gray-500">{{ $accId }}</div>
                                    @endif
                                </td>
                                <td class="p-3 text-right">{{ number_format((float) ($l['debit'] ?? 0), 2, ',', '.') }}</td>
                                <td class="p-3 text-right">{{ number_format((float) ($l['credit'] ?? 0), 2, ',', '.') }}
                                </td>
                                <td class="p-3">{{ $l['memo'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="p-4 text-gray-500" colspan="4">No lines</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (!empty($opening['id']))
                <div class="mt-4">
                    <a class="underline" href="{{ route('finance.journal_entries.edit', $opening['id']) }}">
                        Buka sebagai Journal Entry
                    </a>
                </div>
            @endif
        @endif
    </div>

    @if (!empty($opening) && in_array(auth()->user()->role, ['admin', 'accountant']))
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const accounts = @json($accounts ?? []);
                const bpOptionsUrl = @json(route('finance.business_partners.options'));

                const formEl = document.getElementById('openingEditForm');
                const linesBody = document.getElementById('editLinesBody');
                const btnAddLine = document.getElementById('btnAddEditLine');
                const btnSubmit = document.getElementById('btnEditSubmit');

                const totalDebitEl = document.getElementById('editTotalDebit');
                const totalCreditEl = document.getElementById('editTotalCredit');
                const diffEl = document.getElementById('editDiff');
                const diffWrap = document.getElementById('editDiffWrap');

                const errorsBox = document.getElementById('editLineErrors');
                const errorsList = document.getElementById('editLineErrorsList');

                function escapeHtml(str) {
                    return String(str ?? '').replace(/[&<>"']/g, s => ({
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#39;'
                    })[s]);
                }

                function parseNum(v) {
                    const raw = String(v ?? '').trim();
                    if (!raw) return 0;
                    const s = raw.replace(/\./g, '').replace(',', '.');
                    const n = parseFloat(s);
                    return isFinite(n) ? n : 0;
                }

                function fmt(n) {
                    const v = Number(n || 0);
                    return v.toLocaleString('id-ID', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }

                function getAccountMetaById(id) {
                    const found = accounts.find(a => String(a.id) === String(id));
                    if (!found) return null;
                    return {
                        id: found.id,
                        label: `${found.code} — ${found.name}`.trim(),
                        requires_bp: !!found.requires_bp,
                        subledger: (found.subledger || '').toString().toLowerCase(),
                    };
                }

                function bpCategoriesFor(meta) {
                    if (!meta) return null;
                    const s = (meta.subledger || '').toLowerCase();
                    if (s === 'ar') return ['customer', 'insurer'];
                    if (s === 'ap') return ['supplier', 'insurer'];
                    if (meta.requires_bp) return ['customer', 'supplier', 'insurer'];
                    return null;
                }

                const bpCache = {};

                async function fetchBpOptions(category) {
                    const key = category || 'all';
                    if (bpCache[key]) return bpCache[key];

                    const u = new URL(bpOptionsUrl, window.location.origin);
                    if (category) u.searchParams.set('category', category);
                    u.searchParams.set('q', '');
                    u.searchParams.set('limit', '200');

                    const res = await fetch(u.toString(), {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });

                    if (!res.ok) {
                        const text = await res.text().catch(() => '');
                        throw new Error(`HTTP ${res.status} ${text}`.trim());
                    }

                    const json = await res.json().catch(() => null);
                    if (!json || json.success === false) {
                        throw new Error(String(json?.message || 'Failed to load BP options'));
                    }

                    const data = json?.data;
                    const items =
                        Array.isArray(data?.items) ? data.items :
                        Array.isArray(data) ? data :
                        Array.isArray(json?.items) ? json.items : [];

                    bpCache[key] = items.map(it => ({
                        id: String(it.id || it.value || ''),
                        label: String(it.label || it.name || it.text || it.id || ''),
                    })).filter(it => it.id !== '');

                    return bpCache[key];
                }

                function renderBpSelectOptions(selectEl, items) {
                    // Default option per spec: value null/empty => "(Tidak perlu BP)"
                    selectEl.innerHTML = `<option value="">(Tidak perlu BP)</option>` + (items || []).map(it =>
                        `<option value="${escapeHtml(it.id)}">${escapeHtml(it.label)}</option>`
                    ).join('');
                }

                async function refreshBpUI(tr) {
                    const accountEl = tr.querySelector('.line-account');
                    const bpSelect = tr.querySelector('.line-bp-select');
                    const bpHidden = tr.querySelector('[name="line_bp_id[]"]');
                    const bpHint = tr.querySelector('.bp-hint');
                    if (!accountEl || !bpSelect || !bpHidden) return;

                    const meta = getAccountMetaById(accountEl.value);
                    const cats = bpCategoriesFor(meta);
                    if (!cats) {
                        bpSelect.disabled = true;
                        bpSelect.required = false;
                        renderBpSelectOptions(bpSelect, []);
                        bpSelect.value = '';
                        bpHidden.value = '';
                        if (bpHint) bpHint.textContent = '';
                        return;
                    }

                    bpSelect.disabled = false;
                    bpSelect.required = true;
                    renderBpSelectOptions(bpSelect, []);

                    let items = [];
                    try {
                        const seen = new Set();
                        for (const c of cats) {
                            const arr = await fetchBpOptions(c);
                            arr.forEach(it => {
                                if (seen.has(it.id)) return;
                                seen.add(it.id);
                                items.push(it);
                            });
                        }
                    } catch (e) {
                        bpSelect.disabled = true;
                        bpSelect.required = false;
                        renderBpSelectOptions(bpSelect, []);
                        if (bpHint) {
                            bpHint.textContent = `Gagal load BP: ${String(e?.message || e)}`;
                        }
                        return;
                    }

                    const current = (bpHidden.value || '').trim();
                    const currentId = current !== '' ? current : null;
                    const hasCurrent = currentId && items.some(it => it.id === currentId);

                    // If BP already saved but not present in option list (limit/paging), show it as a fallback option.
                    if (currentId && !hasCurrent) {
                        items = [{ id: currentId, label: `BP: ${currentId}` }, ...items];
                    }

                    renderBpSelectOptions(bpSelect, items);

                    if (currentId) {
                        bpSelect.value = currentId;
                    } else {
                        bpSelect.value = '';
                    }

                    if (bpHint) {
                        bpHint.textContent = meta?.subledger ? `subledger: ${meta.subledger.toUpperCase()}` : '';
                    }
                }

                function renderClientErrors(errs) {
                    if (!errorsBox || !errorsList) return;
                    if (!errs || errs.length === 0) {
                        errorsBox.classList.add('hidden');
                        errorsList.innerHTML = '';
                        return;
                    }
                    errorsBox.classList.remove('hidden');
                    errorsList.innerHTML = errs.map(e => `<li>${escapeHtml(e)}</li>`).join('');
                }

                function recalc() {
                    const rows = Array.from(linesBody?.querySelectorAll('tr') || []);
                    let td = 0,
                        tc = 0,
                        nonZero = 0;

                    rows.forEach(tr => {
                        const d = parseNum(tr.querySelector('.line-debit')?.value);
                        const c = parseNum(tr.querySelector('.line-credit')?.value);
                        td += d;
                        tc += c;
                        if (d > 0 || c > 0) nonZero += 1;
                    });

                    const diff = td - tc;
                    if (totalDebitEl) totalDebitEl.textContent = fmt(td);
                    if (totalCreditEl) totalCreditEl.textContent = fmt(tc);
                    if (diffEl) diffEl.textContent = fmt(diff);

                    const ok = Math.abs(diff) < 0.005 && nonZero >= 2 && (td > 0 || tc > 0);
                    diffWrap?.classList.toggle('bg-green-50', ok);
                    diffWrap?.classList.toggle('bg-red-50', !ok);

                    if (btnSubmit) {
                        btnSubmit.disabled = !ok;
                        btnSubmit.classList.toggle('opacity-60', !ok);
                        btnSubmit.classList.toggle('cursor-not-allowed', !ok);
                    }
                }

                function addLine(prefill = {}) {
                    if (!linesBody) return;

                    const tr = document.createElement('tr');
                    tr.className = 'border-t align-top';

                    const options = ['<option value="">Pilih account...</option>'].concat(accounts.map(a => {
                        const id = String(a.id || '');
                        const label = `${a.code} — ${a.name}`.trim();
                        const sel = String(prefill.account_id || '') === id ? 'selected' : '';
                        const requiresBp = a.requires_bp ? '1' : '0';
                        const subledger = (a.subledger || '');
                        return `<option value="${escapeHtml(id)}" data-subledger="${escapeHtml(subledger)}" data-requires-bp="${requiresBp}" ${sel}>${escapeHtml(label)}</option>`;
                    })).join('');

                    tr.innerHTML = `
                        <td class="p-3">
                            <select name="line_account_id[]" class="border rounded px-2 py-2 w-full line-account">
                                ${options}
                            </select>
                        </td>
                        <td class="p-3">
                            <select class="border rounded px-2 py-2 w-full line-bp-select" disabled>
                                <option value="">(Tidak perlu BP)</option>
                            </select>
                            <input type="hidden" name="line_bp_id[]" value="${escapeHtml(prefill.bp_id || '')}">
                            <div class="text-xs text-gray-500 mt-1 bp-hint"></div>
                        </td>
                        <td class="p-3 text-right">
                            <input name="line_debit[]" value="${escapeHtml(prefill.debit ?? '')}" inputmode="decimal"
                                class="border rounded px-2 py-2 w-full text-right line-debit" placeholder="0">
                        </td>
                        <td class="p-3 text-right">
                            <input name="line_credit[]" value="${escapeHtml(prefill.credit ?? '')}" inputmode="decimal"
                                class="border rounded px-2 py-2 w-full text-right line-credit" placeholder="0">
                        </td>
                        <td class="p-3">
                            <input name="line_memo[]" value="${escapeHtml(prefill.memo ?? '')}"
                                class="border rounded px-2 py-2 w-full" placeholder="memo...">
                        </td>
                        <td class="p-3 text-right">
                            <button type="button" class="px-2 py-2 rounded border hover:bg-gray-50 btn-remove">Remove</button>
                        </td>
                    `;

                    linesBody.appendChild(tr);

                    const account = tr.querySelector('.line-account');
                    const bpSelect = tr.querySelector('.line-bp-select');
                    const bpHidden = tr.querySelector('[name="line_bp_id[]"]');
                    const debit = tr.querySelector('.line-debit');
                    const credit = tr.querySelector('.line-credit');
                    const removeBtn = tr.querySelector('.btn-remove');

                    function onDebitChange() {
                        const d = parseNum(debit.value);
                        if (d > 0) credit.value = '';
                        recalc();
                    }

                    function onCreditChange() {
                        const c = parseNum(credit.value);
                        if (c > 0) debit.value = '';
                        recalc();
                    }

                    debit?.addEventListener('input', onDebitChange);
                    credit?.addEventListener('input', onCreditChange);

                    account?.addEventListener('change', () => {
                        if (bpHidden) bpHidden.value = '';
                        if (bpSelect) bpSelect.value = '';
                        refreshBpUI(tr);
                        recalc();
                    });

                    bpSelect?.addEventListener('change', () => {
                        if (!bpHidden) return;
                        bpHidden.value = (bpSelect.value || '').trim();
                    });

                    removeBtn?.addEventListener('click', () => {
                        tr.remove();
                        recalc();
                    });

                    refreshBpUI(tr);
                    recalc();
                }

                btnAddLine?.addEventListener('click', () => addLine());

                formEl?.addEventListener('submit', (e) => {
                    const rows = Array.from(linesBody?.querySelectorAll('tr') || []);
                    const errors = [];
                    let td = 0,
                        tc = 0,
                        nonZero = 0;

                    rows.forEach((tr, idx) => {
                        const d = parseNum(tr.querySelector('.line-debit')?.value);
                        const c = parseNum(tr.querySelector('.line-credit')?.value);
                        td += d;
                        tc += c;
                        const isNonZero = d > 0 || c > 0;
                        if (isNonZero) nonZero += 1;

                        if (isNonZero && !((d > 0 && c === 0) || (c > 0 && d === 0))) {
                            errors.push(`Baris #${idx + 1}: isi salah satu sisi saja (debit XOR credit)`);
                        }

                        const accountId = (tr.querySelector('.line-account')?.value || '').trim();
                        if (isNonZero && accountId) {
                            const meta = getAccountMetaById(accountId);
                            const cats = bpCategoriesFor(meta);
                            if (cats) {
                                const bpId = (tr.querySelector('[name="line_bp_id[]"]')?.value || '').trim();
                                if (!bpId) {
                                    const suffix = meta?.subledger ? ` (${meta.subledger.toUpperCase()})` : '';
                                    errors.push(`Baris #${idx + 1}: akun ${meta?.label || accountId}${suffix} wajib pilih Business Partner`);
                                }
                            }
                        }
                    });

                    const diff = td - tc;
                    if (nonZero < 2) errors.push('Opening balance must have at least 2 lines');
                    if (!(td > 0 || tc > 0)) errors.push('Total tidak boleh 0');
                    if (Math.abs(diff) >= 0.005) errors.push('Balance check failed: total debit must equal total credit');

                    if (errors.length) {
                        e.preventDefault();
                        renderClientErrors(errors);
                        window.scrollTo({
                            top: 0,
                            behavior: 'smooth'
                        });
                    } else {
                        renderClientErrors([]);
                    }
                });

                const oldIds = @json(old('line_account_id', []));
                const oldDeb = @json(old('line_debit', []));
                const oldCre = @json(old('line_credit', []));
                const oldMem = @json(old('line_memo', []));
                const oldBp = @json(old('line_bp_id', []));

                const openingLines = @json(($opening['lines'] ?? []));

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
                } else if (Array.isArray(openingLines) && openingLines.length) {
                    openingLines.forEach(l => addLine({
                        account_id: l.account_id || '',
                        bp_id: l.bp_id || '',
                        debit: l.debit || '',
                        credit: l.credit || '',
                        memo: l.memo || '',
                    }));
                    addLine(); // placeholder
                } else {
                    addLine();
                    addLine();
                }
            });
        </script>
    @endif
@endsection

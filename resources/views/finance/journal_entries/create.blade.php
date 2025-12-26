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
                                <th class="text-left p-3">Memo</th>
                                <th class="text-right p-3">Debit</th>
                                <th class="text-right p-3">Credit</th>
                                <th class="text-right p-3">#</th>
                            </tr>
                        </thead>
                        <tbody id="linesBody"></tbody>
                        <tfoot>
                            <tr class="border-t bg-gray-50">
                                <td class="p-3 font-semibold" colspan="2">Totals</td>
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
                <option value="{{ $a['id'] }}">
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
                    const debit = Number(tr.querySelector('[name="line_debit[]"]')?.value || 0);
                    const credit = Number(tr.querySelector('[name="line_credit[]"]')?.value || 0);

                    if (!accountId) errs.push(`Line #${i}: account wajib dipilih.`);
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
                const debit = tr.querySelector('[name="line_debit[]"]');
                const credit = tr.querySelector('[name="line_credit[]"]');
                const removeBtn = tr.querySelector('[data-remove]');

                debit.addEventListener('input', () => {
                    if (Number(debit.value || 0) > 0) credit.value = 0;
                    recalc();
                });
                credit.addEventListener('input', () => {
                    if (Number(credit.value || 0) > 0) debit.value = 0;
                    recalc();
                });

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
                        <select name="line_account_id[]" class="border rounded p-2 w-full" required>
                            ${optTpl}
                        </select>
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

                if (prefill.account_id) tr.querySelector('select').value = prefill.account_id;

                bindRow(tr);
                recalc();
            }

            addBtn.addEventListener('click', () => addLineRow());

            // old() restore
            const oldIds = @json(old('line_account_id', []));
            const oldDeb = @json(old('line_debit', []));
            const oldCre = @json(old('line_credit', []));
            const oldMem = @json(old('line_memo', []));

            if (oldIds && oldIds.length) {
                for (let i = 0; i < oldIds.length; i++) {
                    addLineRow({
                        account_id: oldIds[i] || '',
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

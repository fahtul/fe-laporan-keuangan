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
            const linesBody = document.getElementById('linesBody');
            const btnAddLine = document.getElementById('btnAddLine');
            const totalDebitEl = document.getElementById('totalDebit');
            const totalCreditEl = document.getElementById('totalCredit');
            const diffEl = document.getElementById('diff');
            const diffWrap = document.getElementById('diffWrap');
            const btnSubmit = document.getElementById('btnSubmit');

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
                    opts.push(`<option value="${id}" ${sel}>${label}</option>`);
                });
                return opts.join('');
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
                recalc();
            }

            function wireRow(tr) {
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

            // init: minimal 2 baris
            addLine();
            addLine();

            // kalau ada old input (optional): kamu bisa extend isi dari old(), tapi untuk sekarang simple dulu.
        });
    </script>
@endsection

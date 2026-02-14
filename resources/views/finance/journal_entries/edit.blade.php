@extends('finance.layout')
@section('title', 'Edit Journal Entry')
@section('subtitle', 'View / Edit draft, Post, Amend')

@section('header_actions')
    <a href="{{ route('finance.journal_entries.index') }}" class="px-4 py-2 rounded border">Back</a>
@endsection

@section('content')
    @php
        use Illuminate\Support\Str;

        $canWrite = in_array(auth()->user()->role, ['admin', 'accountant']);
        $status = $entry['status'] ?? 'draft';
        $isDraft = $status === 'draft';
        $isPosted = $status === 'posted';

        $entryTypeRaw = (string) ($entry['entry_type'] ?? ($entry['entryType'] ?? ''));
        $entryType = in_array($entryTypeRaw, ['opening', 'closing'], true) ? $entryTypeRaw : 'manual';
        $isOpeningOrClosing = in_array($entryType, ['opening', 'closing'], true);
        $typeBadge =
            $entryType === 'opening'
                ? 'bg-blue-100 text-blue-800'
                : ($entryType === 'closing'
                    ? 'bg-purple-100 text-purple-800'
                    : 'bg-gray-100 text-gray-800');

        // date is DATE-ONLY; avoid timezone shift
        $entryDateRaw = (string) ($entry['date'] ?? now()->toDateString());
        $entryDate = $entryDateRaw !== '' ? substr($entryDateRaw, 0, 10) : now()->toDateString();

        // Backward compatible:
        // - New data: reversal_of_id
        // - Old data: memo prefix "Reversal of ..."
        $memoStr = (string) ($entry['memo'] ?? '');
        $isReversing = !empty($entry['reversal_of_id']) || Str::startsWith($memoStr, 'Reversal of ');
        $isReversingDraft = $isDraft && $isReversing;

        // LOCK HEADER: viewer atau bukan draft => tidak bisa edit header
        $lockHeader = !$canWrite || !$isDraft;

        // LOCK LINES: header terkunci ATAU reversing draft (lines harus read-only)
        $lockLines = $lockHeader || $isReversingDraft;

        $badge = $isPosted
            ? 'bg-green-100 text-green-800'
            : ($isDraft
                ? 'bg-yellow-100 text-yellow-800'
                : 'bg-gray-100 text-gray-800');
    @endphp

    <div class="bg-white border rounded p-4 space-y-4">
        @if (!empty($accountsError))
            <div class="p-3 rounded bg-yellow-100 text-yellow-800">
                {{ $accountsError }}
            </div>
        @endif

        @if (!empty($entry['reversal_of_id']))
            <div class="text-xs text-gray-500">
                Reversal dari: <span class="font-mono">{{ $entry['reversal_of_id'] }}</span>
            </div>
        @elseif ($isReversing)
            <div class="text-xs text-gray-500">
                Entry ini terdeteksi reversal (via memo).
            </div>
        @endif

        {{-- server-side errors --}}
        @if ($errors->any())
            <div class="p-3 rounded bg-red-100 text-red-800">
                <ul class="list-disc ml-5 text-sm">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- client-side errors (lines validation) --}}
        @if ($canWrite && $isDraft && !$lockLines)
            <div id="lineErrors" class="hidden p-3 rounded bg-red-100 text-red-800">
                <div class="font-semibold mb-1">Perbaiki dulu:</div>
                <ul id="lineErrorsList" class="list-disc ml-5 text-sm"></ul>
            </div>
        @endif

        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="text-xs text-gray-500">ID</div>
                <div class="font-mono text-sm">{{ $entry['id'] ?? '' }}</div>
            </div>
            <div class="text-right">
                <div class="flex items-center gap-2 justify-end flex-wrap">
                    <div class="inline-flex px-2 py-1 rounded text-xs {{ $badge }}">{{ $status }}</div>
                    <span class="inline-flex px-2 py-1 rounded text-xs {{ $typeBadge }}">{{ $entryType }}</span>
                </div>
                @if ($isPosted)
                    <div class="text-xs text-gray-500 mt-1">
                        Posted at: {{ $entry['posted_at'] ?? '-' }}
                    </div>
                @endif
            </div>
        </div>

        @if ($isPosted)
            <div class="p-3 rounded bg-indigo-50 border border-indigo-200 text-indigo-900 text-sm">
                Posted tidak bisa diedit/hapus langsung. Gunakan Amend untuk koreksi, agar audit trail aman.
            </div>
        @endif

        {{-- MAIN FORM (UPDATE) --}}
        <form method="POST" action="{{ route('finance.journal_entries.update', $entry['id']) }}" class="space-y-4"
            id="entryForm">
            @csrf
            @method('PUT')

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm mb-1">Date</label>
                    <input type="date" name="date" value="{{ old('date', $entryDate) }}"
                        class="border rounded w-full px-3 py-2" {{ $lockHeader ? 'disabled' : '' }} required>
                </div>

                <div>
                    <label class="block text-sm mb-1">Memo</label>
                    <input name="memo" value="{{ old('memo', $entry['memo'] ?? '') }}"
                        class="border rounded w-full px-3 py-2" {{ $lockHeader ? 'disabled' : '' }}
                        placeholder="{{ $entryType === 'opening' ? 'Opening Balance ' . substr($entryDate, 0, 4) : 'Optional memo...' }}">
                </div>
            </div>

            <div class="border rounded">
                <div class="flex items-center justify-between p-3 border-b bg-gray-50">
                    <div class="font-semibold">Lines</div>

                    @if ($canWrite && $isDraft && !$lockLines)
                        <button type="button" id="addLineBtn" class="px-3 py-1.5 rounded bg-black text-white text-sm">
                            + Add line
                        </button>
                    @else
                        <div class="text-xs text-gray-500">
                            {{ $lockLines ? 'Lines terkunci (reversal)' : 'Read-only' }}
                        </div>
                    @endif
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

                    @if ($isReversingDraft)
                        <div class="p-3 text-xs bg-blue-50 text-blue-700 border-t">
                            Entry ini adalah reversing entry. Lines dikunci agar membatalkan entry asal secara penuh.
                            Jika perlu koreksi sebagian, buat journal koreksi baru.
                        </div>
                    @endif
                </div>

                <div class="p-3 text-xs text-gray-500">
                    Rules: tiap baris hanya boleh isi <b>debit</b> atau <b>credit</b> (bukan dua-duanya). Minimal 2 baris.
                    Draft boleh diedit. Kalau sudah posted, tidak bisa diedit (harus reverse).
                </div>
            </div>

        </form>

        <div class="border-t pt-4">
            <div class="rounded border bg-gray-50 p-3">
                <div class="flex flex-wrap items-center gap-2">
                    @if ($canWrite && $isDraft)
                        <button type="submit" form="entryForm" id="updateBtn"
                            class="px-4 py-2 rounded bg-black text-white text-sm">
                            Update Draft
                        </button>

                        <form id="postForm" method="POST" action="{{ route('finance.journal_entries.post', $entry['id']) }}"
                            class="inline-flex"
                            onsubmit="return confirm('Post entry ini? Setelah posted tidak bisa diedit/hapus langsung.');">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ $idemKey }}">

                            <button type="submit" id="postBtn"
                                class="px-4 py-2 rounded text-white border border-green-800 bg-green-700 text-sm"
                                style="background:#15803d">
                                Post Journal Entry
                            </button>
                        </form>

                        <form method="POST" action="{{ route('finance.journal_entries.destroy', $entry['id']) }}"
                            class="inline-flex" onsubmit="return confirm('Hapus draft jurnal ini?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-4 py-2 rounded border border-red-300 text-red-700 text-sm">
                                Delete Draft
                            </button>
                        </form>
                    @endif

                    @if ($canWrite && $isPosted)
                        <div id="amendSection" class="inline-flex">
                            <button type="button" id="openAmendModal"
                                class="px-4 py-2 rounded bg-indigo-600 text-white hover:bg-indigo-700 text-sm">
                                Amend Posted Journal
                            </button>
                        </div>
                    @endif

                    <a class="px-4 py-2 rounded border text-sm ml-auto"
                        href="{{ route('finance.journal_entries.index') }}">Back</a>
                </div>

                @if ($canWrite && $isDraft)
                    <p class="text-xs text-gray-500 mt-2">
                        Tombol Post aktif jika lines valid dan total debit = total credit &gt; 0.
                    </p>
                @endif
            </div>
        </div>

        @if ($canWrite && $isPosted && !$isOpeningOrClosing)
            <div class="border-t pt-4" id="reverseSection">
                <form method="POST" action="{{ route('finance.journal_entries.reverse', $entry['id']) }}"
                    onsubmit="return confirm('Buat reversing entry dari entry ini?');" class="space-y-2">
                    @csrf

                    <div class="grid md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm mb-1">Reverse Date</label>
                            <input type="date" name="date" value="{{ old('date', $entryDate) }}"
                                class="border rounded w-full px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm mb-1">Reverse Memo (optional)</label>
                            <input name="memo" class="border rounded w-full px-3 py-2" placeholder="Optional...">
                        </div>
                    </div>

                    <button class="px-4 py-2 rounded bg-gray-900 text-white">
                        Create Reversing Entry (Draft)
                    </button>
                </form>
            </div>
        @elseif ($canWrite && $isPosted && $isOpeningOrClosing)
            <div class="border-t pt-4">
                <div class="p-3 rounded bg-gray-50 border text-sm text-gray-700">
                    <span class="font-semibold">Info:</span> Opening/Closing tidak bisa direverse.
                </div>
            </div>
        @elseif ($status === 'void')
            <div class="border-t pt-4">
                <div class="p-3 rounded bg-gray-50 border text-sm text-gray-700">
                    Entry `void`: edit/delete/post/amend dinonaktifkan.
                </div>
            </div>
        @endif
    </div>

    @if ($canWrite && $isPosted)
        <div id="amendModal" class="hidden fixed inset-0 z-50" role="dialog" aria-modal="true"
            aria-labelledby="amendDialogTitle" aria-hidden="true">
            <div class="absolute inset-0 bg-black/40" id="closeAmendModalBg"></div>
            <div id="amendDialogPanel" class="relative max-w-6xl mx-auto mt-8 bg-white rounded border shadow-lg p-4 max-h-[90vh] overflow-auto"
                tabindex="-1">
                <div class="flex items-center justify-between mb-3">
                    <div id="amendDialogTitle" class="font-semibold text-lg">Amend Posted Journal</div>
                    <button type="button" id="closeAmendModalBtn" class="px-3 py-1.5 rounded border">Tutup</button>
                </div>

                <div id="amendErrors" class="hidden p-3 rounded bg-red-100 text-red-800 mb-3">
                    <ul id="amendErrorsList" class="list-disc ml-5 text-sm"></ul>
                </div>

                <form method="POST" id="amendForm" action="{{ route('finance.journal_entries.amend', $entry['id']) }}"
                    class="space-y-4">
                    @csrf

                    <div class="grid md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm mb-1">Reverse Date</label>
                            <input type="date" name="reverse_date" value="{{ old('reverse_date', $entryDate) }}"
                                class="border rounded w-full px-3 py-2" required>
                        </div>
                        <div>
                            <label class="block text-sm mb-1">Reverse Memo (optional)</label>
                            <input name="reverse_memo" value="{{ old('reverse_memo') }}"
                                class="border rounded w-full px-3 py-2" placeholder="Optional...">
                        </div>
                        <div>
                            <label class="block text-sm mb-1">Corrected Date</label>
                            <input type="date" name="date" value="{{ old('date', $entryDate) }}"
                                class="border rounded w-full px-3 py-2" required>
                        </div>
                        <div>
                            <label class="block text-sm mb-1">Corrected Memo (optional)</label>
                            <input name="memo" value="{{ old('memo', $entry['memo'] ?? '') }}"
                                class="border rounded w-full px-3 py-2" placeholder="Optional...">
                        </div>
                    </div>

                    <div class="border rounded">
                        <div class="flex items-center justify-between p-3 border-b bg-gray-50">
                            <div class="font-semibold">Corrected Lines</div>
                            <button type="button" id="amendAddLineBtn"
                                class="px-3 py-1.5 rounded bg-black text-white text-sm">+ Add line</button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
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
                                <tbody id="amendLinesBody"></tbody>
                                <tfoot>
                                    <tr class="border-t bg-gray-50">
                                        <td class="p-3 font-semibold" colspan="3">Totals</td>
                                        <td class="p-3 text-right font-semibold" id="amendTotalDebit">0.00</td>
                                        <td class="p-3 text-right font-semibold" id="amendTotalCredit">0.00</td>
                                        <td class="p-3 text-right">
                                            <span id="amendBalanceBadge"
                                                class="inline-flex px-2 py-1 rounded text-xs bg-gray-100 text-gray-800">
                                                Not balanced
                                            </span>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="submit" id="amendSubmitBtn"
                            class="px-4 py-2 rounded bg-indigo-600 text-white hover:bg-indigo-700">
                            <span id="amendSubmitText">Submit Amend</span>
                        </button>
                        <button type="button" id="amendCancelBtn" class="px-4 py-2 rounded border">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <template id="accountOptionsTemplate">
        <option value="">— Select account —</option>
        @foreach ($accounts as $a)
            @php $isPostable = (bool) ($a['is_postable'] ?? true); @endphp
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
            const lockHeader = @json($lockHeader);
            const lockLines = @json($lockLines);

            const form = document.getElementById('entryForm');
            const postForm = document.getElementById('postForm');

            const linesBody = document.getElementById('linesBody');
            const addBtn = document.getElementById('addLineBtn');
            const optTpl = document.getElementById('accountOptionsTemplate').innerHTML;
            const bpOptionsUrl = @json(route('finance.business_partners.options'));

            const totalDebitEl = document.getElementById('totalDebit');
            const totalCreditEl = document.getElementById('totalCredit');
            const badgeEl = document.getElementById('balanceBadge');

            const errorsBox = document.getElementById('lineErrors');
            const errorsList = document.getElementById('lineErrorsList');

            const updateBtn = document.getElementById('updateBtn'); // can be null
            const postBtn = document.getElementById('postBtn'); // can be null

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
                const bpReadonly = tr.querySelector('.bpReadonly');

                if (!bpBox || !bpSelect || !bpHidden) return;

                if (!categories) {
                    bpBox.classList.add('hidden');
                    if (bpNotRequired) bpNotRequired.classList.remove('hidden');
                    if (bpReadonly) bpReadonly.classList.add('hidden');
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

                if (lockLines) {
                    if (bpReadonly) {
                        let label = (bpLabelHidden && bpLabelHidden.value) ? String(bpLabelHidden.value) : '';

                        if (!label && current) {
                            try {
                                const items = await getBpOptionsForCategories(categories, '');
                                const hit = (items || []).find(it => String(it.id ?? '') === String(current));
                                if (hit) {
                                    const code = String(hit.code ?? '').trim();
                                    const name = String(hit.name ?? '').trim();
                                    label = `${code} - ${name}`.trim();
                                    if (bpLabelHidden) bpLabelHidden.value = label;
                                }
                            } catch (e) {
                                // ignore
                            }
                        }

                        bpReadonly.textContent = label || (current ? current : '-');
                        bpReadonly.classList.remove('hidden');
                    }

                    if (bpSearch) bpSearch.classList.add('hidden');
                    if (bpHint) bpHint.classList.add('hidden');
                    if (bpRequiredNote) bpRequiredNote.classList.add('hidden');
                    return;
                }

                if (bpReadonly) bpReadonly.classList.add('hidden');

                const items = await getBpOptionsForCategories(categories, q);
                renderBpSelectOptions(bpSelect, items);
                bpSelect.value = current;

                if (bpLabelHidden) {
                    const opt = bpSelect && bpSelect.selectedOptions ? bpSelect.selectedOptions[0] : null;
                    bpLabelHidden.value = opt ? String(opt.textContent || '').trim() : '';
                }
            }

            function renderBpSelectOptionsV2(selectEl, items, placeholder) {
                const safePlaceholder = typeof placeholder === 'string' && placeholder !== '' ? placeholder :
                    '— Pilih BP —';
                const opts = [`<option value="">${escapeHtml(safePlaceholder)}</option>`];
                (items || []).forEach(it => {
                    const id = String(it.id ?? '');
                    if (!id) return;
                    const code = String(it.code ?? '');
                    const name = String(it.name ?? '');
                    const label = `${code} - ${name}`.trim();
                    opts.push(`<option value="${escapeHtml(id)}">${escapeHtml(label)}</option>`);
                });
                selectEl.innerHTML = opts.join('');
            }

            async function refreshBpUIV2(tr, q = '') {
                const meta = getAccountMeta(tr);
                const categories = getBpCategoriesForAccount(meta);

                const bpBox = tr.querySelector('.bpBox');
                const bpSelect = tr.querySelector('.bpSelect');
                const bpHidden = tr.querySelector('[name="line_bp_id[]"]');
                const bpHint = tr.querySelector('.bpHint');
                const bpNotRequired = tr.querySelector('.bpNotRequired');
                const bpReadonly = tr.querySelector('.bpReadonly');
                const bpSearch = tr.querySelector('.bpSearch');
                const bpRequiredNote = tr.querySelector('.bpRequiredNote');
                const bpLabelHidden = tr.querySelector('.bpLabelHidden');

                if (!bpBox || !bpSelect || !bpHidden) return;

                if (!categories) {
                    bpBox.classList.add('hidden');
                    if (bpNotRequired) {
                        bpNotRequired.classList.remove('hidden');
                        bpNotRequired.textContent = '(Tidak perlu BP)';
                    }
                    if (bpReadonly) bpReadonly.classList.add('hidden');
                    if (bpRequiredNote) bpRequiredNote.classList.add('hidden');
                    if (bpHint) bpHint.textContent = '';
                    if (!lockLines) {
                        bpHidden.value = '';
                        bpSelect.innerHTML = '<option value="">— Pilih BP —</option>';
                        if (bpSearch) {
                            bpSearch.value = '';
                            bpSearch.setAttribute('placeholder', 'Cari BP...');
                        }
                    }
                    if (bpLabelHidden) bpLabelHidden.value = '';
                    return;
                }

                bpBox.classList.remove('hidden');
                if (bpNotRequired) bpNotRequired.classList.add('hidden');
                if (bpRequiredNote) bpRequiredNote.classList.remove('hidden');
                if (bpSearch) bpSearch.setAttribute('placeholder', 'Cari BP (wajib)...');

                const hintParts = [];
                if (meta.subledger) hintParts.push(`subledger: ${meta.subledger.toUpperCase()}`);
                if (categories.length === 1) hintParts.push(`filter: ${categories[0]}`);
                if (categories.length > 1) hintParts.push(`filter: ${categories.join(' / ')}`);
                if (categories.length === 0) hintParts.push('filter: all');
                if (bpHint) bpHint.textContent = hintParts.join(' › ');

                const current = bpHidden.value || '';

                if (lockLines) {
                    if (bpReadonly) {
                        let label = (bpLabelHidden && bpLabelHidden.value) ? String(bpLabelHidden.value) : '';

                        if (!label && current) {
                            try {
                                const items = await getBpOptionsForCategories(categories, '');
                                const hit = (items || []).find(it => String(it.id ?? '') === String(current));
                                if (hit) {
                                    const code = String(hit.code ?? '').trim();
                                    const name = String(hit.name ?? '').trim();
                                    label = `${code} - ${name}`.trim();
                                    if (bpLabelHidden) bpLabelHidden.value = label;
                                }
                            } catch (e) {
                                // ignore
                            }
                        }

                        bpReadonly.textContent = label || (current ? current : '-');
                        bpReadonly.classList.remove('hidden');
                    }

                    if (bpSearch) bpSearch.classList.add('hidden');
                    if (bpHint) bpHint.classList.add('hidden');
                    if (bpRequiredNote) bpRequiredNote.classList.add('hidden');
                    return;
                }

                if (bpReadonly) bpReadonly.classList.add('hidden');

                const items = await getBpOptionsForCategories(categories, q);
                renderBpSelectOptionsV2(bpSelect, items, '— Pilih BP (wajib) —');
                bpSelect.value = current;
            }

            function computeTotals() {
                let td = 0,
                    tc = 0;

                getRows().forEach(tr => {
                    const debit = Number(tr.querySelector('[name="line_debit[]"]')?.value || 0);
                    const credit = Number(tr.querySelector('[name="line_credit[]"]')?.value || 0);
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
                    if (!lockLines && accountId && needsBp && !bpId) {
                        if (meta.subledger === 'ar' || meta.subledger === 'ap') {
                            errs.push(`Baris #${i}: akun AR/AP wajib pilih BP.`);
                        } else if (meta.requiresBp) {
                            errs.push(`Baris #${i}: akun ini wajib pilih BP.`);
                        } else {
                            errs.push(`Baris #${i}: BP wajib dipilih untuk account ini.`);
                        }
                    }
                    if (debit < 0 || credit < 0) errs.push(`Line #${i}: debit/credit tidak boleh negatif.`);
                    if (debit > 0 && credit > 0) errs.push(
                        `Line #${i}: tidak boleh isi debit dan credit sekaligus.`);
                    if (debit === 0 && credit === 0) errs.push(`Line #${i}: debit atau credit harus > 0.`);
                });

                return errs;
            }

            function renderErrors(errs) {
                // kalau box tidak ada (lockLines), jangan render
                if (!errorsBox || !errorsList) return;
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

            function ensureHidden(tr, name, value) {
                // hindari double-hidden kalau recalc dipanggil berkali-kali
                const key = `data-hidden-${name.replace(/\W+/g,'_')}`;
                if (tr.hasAttribute(key)) return;
                tr.setAttribute(key, '1');

                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value ?? '';
                tr.appendChild(input);
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

                // hanya tampilkan error box kalau memang tersedia (draft normal)
                if (!lockLines) renderErrors(errs);

                // Update Draft: harus valid (tidak wajib balanced)
                setBtnDisabled(updateBtn, errs.length > 0);

                // Post: harus valid + balanced
                setBtnDisabled(postBtn, errs.length > 0 || !balanced);
            }

            function bindRow(tr) {
                const accountSel = tr.querySelector('.accountSelect');
                const memoInp = tr.querySelector('[name="line_memo[]"]');
                const debit = tr.querySelector('[name="line_debit[]"]');
                const credit = tr.querySelector('[name="line_credit[]"]');
	                const removeBtn = tr.querySelector('[data-remove]');
	                const bpSelect = tr.querySelector('.bpSelect');
	                const bpHidden = tr.querySelector('[name="line_bp_id[]"]');
	                const bpLabelHidden = tr.querySelector('.bpLabelHidden');
	                const bpSearch = tr.querySelector('.bpSearch');
	                let bpTimer = null;

                if (lockLines) {
                    // Kunci UI tapi tetap submit nilai (disabled field tidak terkirim)
                    if (accountSel) {
                        accountSel.setAttribute('disabled', 'disabled');
                        ensureHidden(tr, 'line_account_id[]', accountSel.value);
                    }
                    if (memoInp) {
                        memoInp.setAttribute('readonly', 'readonly');
                        memoInp.classList.add('bg-gray-50');
                        ensureHidden(tr, 'line_memo[]', memoInp.value);
                    }
                    if (debit) {
                        debit.setAttribute('readonly', 'readonly');
                        debit.classList.add('bg-gray-50');
                        ensureHidden(tr, 'line_debit[]', debit.value);
                    }
                    if (credit) {
                        credit.setAttribute('readonly', 'readonly');
                        credit.classList.add('bg-gray-50');
                        ensureHidden(tr, 'line_credit[]', credit.value);
                    }
                    if (bpSelect) {
                        bpSelect.setAttribute('disabled', 'disabled');
                        bpSelect.classList.add('bg-gray-50');
                    }
                    if (bpSearch) {
                        bpSearch.setAttribute('readonly', 'readonly');
                        bpSearch.classList.add('bg-gray-50');
                    }

                    if (removeBtn) removeBtn.style.display = 'none';
                    refreshBpUIV2(tr, '');
                    return;
                }

                // normal editable
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

	                if (accountSel) {
	                    accountSel.addEventListener('change', () => {
	                        if (bpHidden) bpHidden.value = '';
	                        if (bpLabelHidden) bpLabelHidden.value = '';
	                        if (bpSelect) bpSelect.value = '';
	                        refreshBpUIV2(tr, '').then(recalc);
	                    });
	                }

	                if (bpSelect && bpHidden) {
	                    bpSelect.addEventListener('change', () => {
	                        bpHidden.value = bpSelect.value || '';
	                        if (bpLabelHidden) {
	                            const opt = bpSelect.selectedOptions ? bpSelect.selectedOptions[0] : null;
	                            bpLabelHidden.value = opt ? String(opt.textContent || '').trim() : '';
	                        }
	                        recalc();
	                    });
	                }

                if (bpSearch) {
                    bpSearch.addEventListener('input', () => {
                        clearTimeout(bpTimer);
                        bpTimer = setTimeout(() => {
                            refreshBpUIV2(tr, (bpSearch.value || '').trim());
                        }, 300);
                    });
                }

                if (removeBtn) {
                    removeBtn.addEventListener('click', () => {
                        tr.remove();
                        recalc();
                    });
                }
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
 	                            <input type="hidden" class="bpLabelHidden" value="${escapeHtml(prefill.bp_label || '')}">
 	                            <input type="text" class="bpSearch border rounded px-3 py-2 w-full mt-2 text-sm"
 	                                placeholder="Cari BP..." value="">
                                <div class="bpRequiredNote hidden text-xs text-red-600 mt-1">wajib</div>
 	                            <div class="bpHint text-xs text-gray-500 mt-1"></div>
 	                            <div class="bpReadonly hidden text-xs text-gray-600 mt-1"></div>
 	                        </div>
	                        <div class="bpNotRequired text-gray-400">(Tidak perlu BP)</div>
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
                const bpHidden = tr.querySelector('[name="line_bp_id[]"]');
                const bpSelect = tr.querySelector('.bpSelect');
                if (bpHidden && bpSelect) bpSelect.value = bpHidden.value || '';

                bindRow(tr);
                refreshBpUIV2(tr, '');
                recalc();
            }

            if (addBtn && !lockLines) addBtn.addEventListener('click', () => addLineRow());

            // Prefill from old() OR from entry lines
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
                const entryLines = @json($entry['lines'] ?? []);
                (entryLines || []).forEach(l => addLineRow({
                    account_id: l.account_id,
                    bp_id: l.bp_id || l.bpId || '',
                    debit: Number(l.debit || 0),
                    credit: Number(l.credit || 0),
                    memo: l.memo || '',
                }));

                if (!entryLines || entryLines.length === 0) {
                    addLineRow({});
                    addLineRow({});
                }
            }

            // guard submit update form
            if (form && !lockHeader) {
                form.addEventListener('submit', function(e) {
                    // kalau lockLines, biarkan submit (nilai sudah dipasok via hidden)
                    if (!lockLines) {
                        const errs = validateRows();
                        renderErrors(errs);
                        if (errs.length) {
                            e.preventDefault();
                            e.stopPropagation();
                        }
                    }
                });
            }

            // guard submit POST form (penting karena form terpisah)
            if (postForm && !lockHeader) {
                postForm.addEventListener('submit', function(e) {
                    const errs = validateRows();
                    const {
                        balanced
                    } = computeTotals();

                    if (errs.length) {
                        if (errorsBox) renderErrors(errs);
                        else alert(errs.join('\n'));
                        e.preventDefault();
                        e.stopPropagation();
                        return;
                    }

                    if (!balanced) {
                        const msg = 'Tidak bisa POST: total debit harus sama dengan total credit dan > 0.';
                        if (errorsBox) renderErrors([msg]);
                        else alert(msg);
                        e.preventDefault();
                        e.stopPropagation();
                        return;
                    }
                });
            }

            const canAmend = @json($canWrite && $isPosted);
            if (canAmend) {
                const amendModal = document.getElementById('amendModal');
                const amendDialogPanel = document.getElementById('amendDialogPanel');
                const openAmendModalBtn = document.getElementById('openAmendModal');
                const closeAmendModalBg = document.getElementById('closeAmendModalBg');
                const closeAmendModalBtn = document.getElementById('closeAmendModalBtn');
                const amendCancelBtn = document.getElementById('amendCancelBtn');
                const amendForm = document.getElementById('amendForm');
                const amendLinesBody = document.getElementById('amendLinesBody');
                const amendAddLineBtn = document.getElementById('amendAddLineBtn');
                const amendErrors = document.getElementById('amendErrors');
                const amendErrorsList = document.getElementById('amendErrorsList');
                const amendTotalDebit = document.getElementById('amendTotalDebit');
                const amendTotalCredit = document.getElementById('amendTotalCredit');
                const amendBalanceBadge = document.getElementById('amendBalanceBadge');
                const amendSubmitBtn = document.getElementById('amendSubmitBtn');
                const amendSubmitText = document.getElementById('amendSubmitText');
                const amendReverseDateInput = amendForm?.querySelector('input[name="reverse_date"]');

                let isAmendSubmitting = false;
                let lastFocusedBeforeModal = null;

                function setAmendSubmittingState(submitting) {
                    isAmendSubmitting = !!submitting;

                    if (amendSubmitBtn) {
                        amendSubmitBtn.disabled = isAmendSubmitting;
                        amendSubmitBtn.classList.toggle('opacity-60', isAmendSubmitting);
                        amendSubmitBtn.classList.toggle('cursor-not-allowed', isAmendSubmitting);
                    }

                    if (amendSubmitText) {
                        amendSubmitText.textContent = isAmendSubmitting ? 'Submitting...' : 'Submit Amend';
                    }

                    if (closeAmendModalBtn) closeAmendModalBtn.disabled = isAmendSubmitting;
                    if (amendCancelBtn) amendCancelBtn.disabled = isAmendSubmitting;
                    if (amendAddLineBtn) amendAddLineBtn.disabled = isAmendSubmitting;
                }

                function openAmendModal() {
                    if (!amendModal) return;
                    lastFocusedBeforeModal = document.activeElement;
                    amendModal.classList.remove('hidden');
                    amendModal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('overflow-hidden');
                    setTimeout(() => {
                        if (amendReverseDateInput) {
                            amendReverseDateInput.focus();
                        } else if (amendDialogPanel) {
                            amendDialogPanel.focus();
                        }
                    }, 0);
                }

                function closeAmendModal() {
                    if (isAmendSubmitting) return;
                    if (!amendModal) return;
                    amendModal.classList.add('hidden');
                    amendModal.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('overflow-hidden');
                    if (lastFocusedBeforeModal && typeof lastFocusedBeforeModal.focus === 'function') {
                        lastFocusedBeforeModal.focus();
                    }
                }

                function renderAmendErrors(errors) {
                    if (!amendErrors || !amendErrorsList) return;
                    if (!errors.length) {
                        amendErrors.classList.add('hidden');
                        amendErrorsList.innerHTML = '';
                        return;
                    }
                    amendErrors.classList.remove('hidden');
                    amendErrorsList.innerHTML = errors.map(e => `<li>${escapeHtml(e)}</li>`).join('');
                }

                function getAmendRows() {
                    return Array.from(amendLinesBody.querySelectorAll('tr'));
                }

                function getAmendAccountMeta(tr) {
                    const sel = tr.querySelector('.amend-account-select');
                    const opt = sel && sel.selectedOptions ? sel.selectedOptions[0] : null;
                    return {
                        subledger: (opt?.dataset?.subledger || '').toLowerCase(),
                        requiresBp: (opt?.dataset?.requiresBp || '') === '1',
                    };
                }

                async function refreshAmendBpUI(tr, q = '') {
                    const meta = getAmendAccountMeta(tr);
                    const categories = getBpCategoriesForAccount(meta);

                    const bpBox = tr.querySelector('.amend-bp-box');
                    const bpSelect = tr.querySelector('.amend-bp-select');
                    const bpHidden = tr.querySelector('[name="amend_line_bp_id[]"]');
                    const bpHint = tr.querySelector('.amend-bp-hint');
                    const bpSearch = tr.querySelector('.amend-bp-search');
                    const bpNotRequired = tr.querySelector('.amend-bp-not-required');

                    if (!bpBox || !bpSelect || !bpHidden) return;

                    if (!categories) {
                        bpBox.classList.add('hidden');
                        if (bpNotRequired) bpNotRequired.classList.remove('hidden');
                        if (bpSearch) bpSearch.value = '';
                        bpHidden.value = '';
                        bpSelect.innerHTML = '<option value="">-- Select BP --</option>';
                        if (bpHint) bpHint.textContent = '';
                        return;
                    }

                    bpBox.classList.remove('hidden');
                    if (bpNotRequired) bpNotRequired.classList.add('hidden');

                    const items = await getBpOptionsForCategories(categories, q);
                    const opts = ['<option value="">-- Select BP --</option>'];
                    (items || []).forEach(it => {
                        const id = String(it.id ?? '');
                        if (!id) return;
                        const code = String(it.code ?? '');
                        const name = String(it.name ?? '');
                        const label = `${code} - ${name}`.trim();
                        opts.push(`<option value="${escapeHtml(id)}">${escapeHtml(label)}</option>`);
                    });
                    bpSelect.innerHTML = opts.join('');
                    bpSelect.value = (bpHidden.value || '').trim();

                    if (bpHint) {
                        const parts = [];
                        if (meta.subledger) parts.push(`subledger: ${meta.subledger.toUpperCase()}`);
                        if (categories.length === 1) parts.push(`filter: ${categories[0]}`);
                        if (categories.length > 1) parts.push(`filter: ${categories.join(' / ')}`);
                        if (categories.length === 0) parts.push('filter: all');
                        bpHint.textContent = parts.join(' | ');
                    }
                }

                function computeAmendTotals() {
                    let td = 0;
                    let tc = 0;
                    getAmendRows().forEach(tr => {
                        td += Number(tr.querySelector('[name="amend_line_debit[]"]')?.value || 0);
                        tc += Number(tr.querySelector('[name="amend_line_credit[]"]')?.value || 0);
                    });
                    const balanced = Math.round(td * 100) === Math.round(tc * 100) && td > 0;
                    return {
                        td,
                        tc,
                        balanced
                    };
                }

                function validateAmendRows() {
                    const errors = [];
                    const rows = getAmendRows();
                    if (rows.length < 2) errors.push('Minimal 2 lines.');

                    rows.forEach((tr, idx) => {
                        const accountId = (tr.querySelector('[name="amend_line_account_id[]"]')?.value || '').trim();
                        const debit = Number(tr.querySelector('[name="amend_line_debit[]"]')?.value || 0);
                        const credit = Number(tr.querySelector('[name="amend_line_credit[]"]')?.value || 0);
                        const meta = getAmendAccountMeta(tr);
                        const needsBp = getBpCategoriesForAccount(meta) !== null;
                        const bpId = (tr.querySelector('[name="amend_line_bp_id[]"]')?.value || '').trim();

                        if (!accountId) errors.push(`Line #${idx + 1}: account wajib dipilih.`);
                        if (debit < 0 || credit < 0) errors.push(`Line #${idx + 1}: debit/credit tidak boleh negatif.`);
                        if (debit > 0 && credit > 0) errors.push(`Line #${idx + 1}: isi debit atau credit saja (XOR).`);
                        if (debit === 0 && credit === 0) errors.push(`Line #${idx + 1}: debit atau credit harus > 0.`);
                        if (accountId && needsBp && !bpId) {
                            errors.push(`Line #${idx + 1}: akun AR/AP atau requires_bp wajib pilih BP.`);
                        }
                    });

                    const {
                        td,
                        tc
                    } = computeAmendTotals();
                    if (!(td > 0 || tc > 0)) errors.push('Total tidak boleh 0.');
                    if (Math.abs(td - tc) >= 0.005) errors.push('Balance check failed: total debit must equal total credit.');

                    return errors;
                }

                function focusFirstAmendErrorField() {
                    const rows = getAmendRows();
                    for (let i = 0; i < rows.length; i++) {
                        const tr = rows[i];
                        const accountEl = tr.querySelector('[name="amend_line_account_id[]"]');
                        const debitEl = tr.querySelector('[name="amend_line_debit[]"]');
                        const creditEl = tr.querySelector('[name="amend_line_credit[]"]');
                        const bpEl = tr.querySelector('.amend-bp-select');

                        const accountId = (accountEl?.value || '').trim();
                        const debit = Number(debitEl?.value || 0);
                        const credit = Number(creditEl?.value || 0);
                        const meta = getAmendAccountMeta(tr);
                        const needsBp = getBpCategoriesForAccount(meta) !== null;
                        const bpId = (tr.querySelector('[name="amend_line_bp_id[]"]')?.value || '').trim();

                        if (!accountId) {
                            accountEl?.focus?.();
                            return;
                        }
                        if (debit < 0 || credit < 0 || (debit > 0 && credit > 0) || (debit === 0 && credit === 0)) {
                            debitEl?.focus?.();
                            return;
                        }
                        if (accountId && needsBp && !bpId) {
                            bpEl?.focus?.();
                            return;
                        }
                    }
                }

                function recalcAmend() {
                    const {
                        td,
                        tc,
                        balanced
                    } = computeAmendTotals();
                    if (amendTotalDebit) amendTotalDebit.textContent = money(td);
                    if (amendTotalCredit) amendTotalCredit.textContent = money(tc);
                    if (amendBalanceBadge) {
                        amendBalanceBadge.textContent = balanced ? 'Balanced' : 'Not balanced';
                        amendBalanceBadge.className = 'inline-flex px-2 py-1 rounded text-xs ' + (balanced ?
                            'bg-green-100 text-green-800' :
                            'bg-gray-100 text-gray-800');
                    }
                }

                function bindAmendRow(tr) {
                    const accountSel = tr.querySelector('.amend-account-select');
                    const debitInput = tr.querySelector('[name="amend_line_debit[]"]');
                    const creditInput = tr.querySelector('[name="amend_line_credit[]"]');
                    const removeBtn = tr.querySelector('[data-amend-remove]');
                    const bpSelect = tr.querySelector('.amend-bp-select');
                    const bpHidden = tr.querySelector('[name="amend_line_bp_id[]"]');
                    const bpSearch = tr.querySelector('.amend-bp-search');
                    let bpTimer = null;

                    if (debitInput) {
                        debitInput.addEventListener('input', () => {
                            if (Number(debitInput.value || 0) > 0 && creditInput) creditInput.value = 0;
                            recalcAmend();
                        });
                    }
                    if (creditInput) {
                        creditInput.addEventListener('input', () => {
                            if (Number(creditInput.value || 0) > 0 && debitInput) debitInput.value = 0;
                            recalcAmend();
                        });
                    }

                    tr.querySelectorAll('select,input').forEach(el => {
                        el.addEventListener('change', recalcAmend);
                    });

                    if (accountSel) {
                        accountSel.addEventListener('change', () => {
                            if (bpHidden) bpHidden.value = '';
                            if (bpSelect) bpSelect.value = '';
                            refreshAmendBpUI(tr, '').then(recalcAmend);
                        });
                    }

                    if (bpSelect && bpHidden) {
                        bpSelect.addEventListener('change', () => {
                            bpHidden.value = bpSelect.value || '';
                            recalcAmend();
                        });
                    }

                    if (bpSearch) {
                        bpSearch.addEventListener('input', () => {
                            clearTimeout(bpTimer);
                            bpTimer = setTimeout(() => {
                                refreshAmendBpUI(tr, (bpSearch.value || '').trim());
                            }, 300);
                        });
                    }

                    if (removeBtn) {
                        removeBtn.addEventListener('click', () => {
                            tr.remove();
                            recalcAmend();
                        });
                    }
                }

                function addAmendLine(prefill = {}) {
                    const tr = document.createElement('tr');
                    tr.className = 'border-t';
                    tr.innerHTML = `
                        <td class="p-3">
                            <select name="amend_line_account_id[]" class="border rounded p-2 w-full amend-account-select" required>
                                ${optTpl}
                            </select>
                        </td>
                        <td class="p-3">
                            <div class="amend-bp-box hidden">
                                <select class="border rounded p-2 w-full amend-bp-select">
                                    <option value="">-- Select BP --</option>
                                </select>
                                <input type="hidden" name="amend_line_bp_id[]" value="${escapeHtml(prefill.bp_id || '')}">
                                <input type="text" class="amend-bp-search border rounded px-3 py-2 w-full mt-2 text-sm" placeholder="Cari BP...">
                                <div class="amend-bp-hint text-xs text-gray-500 mt-1"></div>
                            </div>
                            <div class="amend-bp-not-required text-gray-400">(Tidak perlu BP)</div>
                        </td>
                        <td class="p-3">
                            <input name="amend_line_memo[]" class="border rounded px-3 py-2 w-full" value="${escapeHtml(prefill.memo || '')}">
                        </td>
                        <td class="p-3 text-right">
                            <input type="number" step="0.01" min="0" name="amend_line_debit[]" class="border rounded px-3 py-2 w-32 text-right" value="${Number(prefill.debit ?? 0)}">
                        </td>
                        <td class="p-3 text-right">
                            <input type="number" step="0.01" min="0" name="amend_line_credit[]" class="border rounded px-3 py-2 w-32 text-right" value="${Number(prefill.credit ?? 0)}">
                        </td>
                        <td class="p-3 text-right">
                            <button type="button" data-amend-remove class="underline text-red-600">Remove</button>
                        </td>
                    `;

                    amendLinesBody.appendChild(tr);
                    if (prefill.account_id) {
                        tr.querySelector('[name="amend_line_account_id[]"]').value = prefill.account_id;
                    }

                    bindAmendRow(tr);
                    refreshAmendBpUI(tr, '');
                    recalcAmend();
                }

                if (openAmendModalBtn) openAmendModalBtn.addEventListener('click', openAmendModal);
                if (closeAmendModalBg) closeAmendModalBg.addEventListener('click', closeAmendModal);
                if (closeAmendModalBtn) closeAmendModalBtn.addEventListener('click', closeAmendModal);
                if (amendCancelBtn) amendCancelBtn.addEventListener('click', closeAmendModal);
                if (amendAddLineBtn) amendAddLineBtn.addEventListener('click', () => addAmendLine({}));

                document.addEventListener('keydown', function(e) {
                    if (!amendModal || amendModal.classList.contains('hidden')) return;
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        closeAmendModal();
                    }
                });

                const oldAmendIds = @json(old('amend_line_account_id', []));
                const oldAmendDeb = @json(old('amend_line_debit', []));
                const oldAmendCre = @json(old('amend_line_credit', []));
                const oldAmendMem = @json(old('amend_line_memo', []));
                const oldAmendBp = @json(old('amend_line_bp_id', []));

                if (oldAmendIds && oldAmendIds.length) {
                    for (let i = 0; i < oldAmendIds.length; i++) {
                        addAmendLine({
                            account_id: oldAmendIds[i] || '',
                            bp_id: oldAmendBp[i] || '',
                            debit: Number(oldAmendDeb[i] || 0),
                            credit: Number(oldAmendCre[i] || 0),
                            memo: oldAmendMem[i] || '',
                        });
                    }
                    openAmendModal();
                } else {
                    const entryLines = @json($entry['lines'] ?? []);
                    (entryLines || []).forEach(l => addAmendLine({
                        account_id: l.account_id || '',
                        bp_id: l.bp_id || l.bpId || '',
                        debit: Number(l.debit || 0),
                        credit: Number(l.credit || 0),
                        memo: l.memo || '',
                    }));
                    if (!entryLines || entryLines.length === 0) {
                        addAmendLine({});
                        addAmendLine({});
                    }
                }

                if (window.location.hash === '#amendSection') {
                    openAmendModal();
                }

                if (amendForm) {
                    amendForm.addEventListener('submit', function(e) {
                        if (isAmendSubmitting) {
                            e.preventDefault();
                            e.stopPropagation();
                            return;
                        }

                        const errors = validateAmendRows();
                        renderAmendErrors(errors);
                        if (errors.length) {
                            e.preventDefault();
                            e.stopPropagation();
                            focusFirstAmendErrorField();
                            return;
                        }

                        setAmendSubmittingState(true);
                    });
                }
            }

            // init calc + button states
            recalc();
        });
    </script>
@endsection

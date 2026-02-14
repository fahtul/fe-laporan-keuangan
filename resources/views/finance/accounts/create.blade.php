@extends('finance.layout')
@section('title', 'Create Account')

@section('content')
    <div class="bg-white border rounded p-4 max-w-xl">
        @if (!empty($parentsError))
            <div class="p-3 rounded bg-yellow-100 text-yellow-800 mb-3">
                {{ $parentsError }}
            </div>
        @endif

        @if (session('restoreCandidate'))
            @php $c = session('restoreCandidate'); @endphp
            <div class="p-3 rounded bg-yellow-50 border border-yellow-200 text-yellow-900 mb-3">
                <div class="font-semibold mb-1">Account dengan code ini pernah dibuat & sedang terhapus.</div>
                <div class="text-sm">
                    <div><span class="font-medium">Code:</span> {{ $c['code'] ?? '-' }}</div>
                    <div><span class="font-medium">Name:</span> {{ $c['name'] ?? '-' }}</div>
                    <div><span class="font-medium">Type:</span> {{ $c['type'] ?? '-' }}</div>
                </div>

                <div class="mt-3 flex gap-2">
                    <form method="POST" action="{{ route('finance.accounts.restore', $c['id']) }}">
                        @csrf
                        <button class="px-4 py-2 rounded bg-yellow-600 text-white">
                            Restore Account
                        </button>
                    </form>

                    <a href="{{ route('finance.accounts.edit', $c['id']) }}" class="px-4 py-2 rounded border">
                        Lihat / Edit
                    </a>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('finance.accounts.store') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm mb-1">Code</label>
                <input name="code" value="{{ old('code') }}" class="border rounded w-full px-3 py-2" required>
            </div>

            <div>
                <label class="block text-sm mb-1">Name</label>
                <input name="name" value="{{ old('name') }}" class="border rounded w-full px-3 py-2" required>
            </div>

            <div>
                <label class="block text-sm mb-1">Cash Flow Category (optional)</label>
                @php $cfOld = (string) old('cf_activity', ''); @endphp
                <select name="cf_activity" class="border rounded p-2 w-full">
                    <option value="" {{ $cfOld === '' ? 'selected' : '' }}>— Default (Operating) —</option>
                    <option value="cash" {{ $cfOld === 'cash' ? 'selected' : '' }}>cash</option>
                    <option value="operating" {{ $cfOld === 'operating' ? 'selected' : '' }}>operating</option>
                    <option value="investing" {{ $cfOld === 'investing' ? 'selected' : '' }}>investing</option>
                    <option value="financing" {{ $cfOld === 'financing' ? 'selected' : '' }}>financing</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Dipakai oleh report Arus Kas (Cash Flow).</p>
            </div>
            <div>
                <label class="block text-sm mb-1">P&L Category (optional)</label>
                @php $plOld = (string) old('pl_category', ''); @endphp
                <select name="pl_category" class="border rounded p-2 w-full">
                    <option value="" {{ $plOld === '' ? 'selected' : '' }}>-- None --</option>
                    <option value="revenue" {{ $plOld === 'revenue' ? 'selected' : '' }}>Revenue</option>
                    <option value="cogs" {{ $plOld === 'cogs' ? 'selected' : '' }}>COGS/HPP</option>
                    <option value="opex" {{ $plOld === 'opex' ? 'selected' : '' }}>Operating Expense</option>
                    <option value="depreciation_amortization" {{ $plOld === 'depreciation_amortization' ? 'selected' : '' }}>Depreciation & Amortization</option>
                    <option value="non_operating" {{ $plOld === 'non_operating' ? 'selected' : '' }}>Non Operating</option>
                    <option value="other" {{ $plOld === 'other' ? 'selected' : '' }}>Other</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Dipakai untuk klasifikasi akun P&L, termasuk komponen EBITDA.</p>
            </div>
            {{-- TYPE + NORMAL BALANCE (auto via JS) --}}
            <div>
                <label class="block text-sm mb-1">Type</label>
                <select id="typeSelect" name="type" class="border rounded p-2 w-full">
                    @php $typeOld = old('type', 'asset'); @endphp
                    <option value="asset" {{ $typeOld === 'asset' ? 'selected' : '' }}>asset</option>
                    <option value="liability" {{ $typeOld === 'liability' ? 'selected' : '' }}>liability</option>
                    <option value="equity" {{ $typeOld === 'equity' ? 'selected' : '' }}>equity</option>
                    <option value="revenue" {{ $typeOld === 'revenue' ? 'selected' : '' }}>revenue</option>
                    <option value="expense" {{ $typeOld === 'expense' ? 'selected' : '' }}>expense</option>
                </select>

                <label class="block text-sm mt-4 mb-1">Normal Balance (auto)</label>
                <input id="normalBalance" type="text" readonly class="border rounded p-2 w-full bg-gray-100"
                    value="" />
            </div>

            {{-- PARENT ACCOUNT --}}
            <div>
                <label class="block text-sm mb-1">Parent Account (optional)</label>
                <select id="parentSelect" name="parent_id" class="border rounded p-2 w-full">
                    <option value="">— No Parent —</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">
                    Parent biasanya akun header/grup (is_postable = false) dan harus satu type.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="border rounded p-3">
                    <input type="hidden" name="is_postable" value="0">
                    @php $postableOld = old('is_postable', '1'); @endphp
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="is_postable" value="1" class="rounded"
                            {{ (string) $postableOld === '1' ? 'checked' : '' }}>
                        <span class="text-sm font-medium">Postable</span>
                    </label>
                    <p class="text-xs text-gray-500 mt-1">Matikan jika akun ini hanya header/grup.</p>
                </div>

                <div class="border rounded p-3">
                    <input type="hidden" name="is_active" value="0">
                    @php $activeOld = old('is_active', '1'); @endphp
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" class="rounded"
                            {{ (string) $activeOld === '1' ? 'checked' : '' }}>
                        <span class="text-sm font-medium">Active</span>
                    </label>
                    <p class="text-xs text-gray-500 mt-1">Nonaktifkan jika tidak dipakai.</p>
                </div>
            </div>

            <div class="border rounded p-3 space-y-3">
                <div>
                    <input type="hidden" name="requires_bp" value="0">
                    @php $requiresBpOld = old('requires_bp', '0'); @endphp
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="requires_bp" value="1" class="rounded"
                            {{ (string) $requiresBpOld === '1' ? 'checked' : '' }}>
                        <span class="text-sm font-medium">Requires Business Partner</span>
                    </label>
                    <p class="text-xs text-gray-500 mt-1">Centang jika transaksi akun ini wajib pilih BP.</p>
                </div>

                <div>
                    <label class="block text-sm mb-1">Subledger (optional)</label>
                    @php $subledgerOld = (string) old('subledger', ''); @endphp
                    <select name="subledger" class="border rounded p-2 w-full">
                        <option value="" {{ $subledgerOld === '' ? 'selected' : '' }}>— None —</option>
                        <option value="ar" {{ $subledgerOld === 'ar' ? 'selected' : '' }}>ar</option>
                        <option value="ap" {{ $subledgerOld === 'ap' ? 'selected' : '' }}>ap</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Isi jika akun ini punya subledger AR/AP (opsional).</p>
                </div>
            </div>

            <div class="flex gap-2">
                <button class="px-4 py-2 rounded bg-black text-white">Save</button>
                <a class="px-4 py-2 rounded border" href="{{ route('finance.accounts.index') }}">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const typeSelect = document.getElementById('typeSelect');
            const normalBalance = document.getElementById('normalBalance');
            const parentSelect = document.getElementById('parentSelect');

            const parents = @json($parents ?? []);
            const parentOld = @json(old('parent_id', ''));

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

            function renderParentOptions() {
                const currentType = typeSelect.value;
                parentSelect.innerHTML = '<option value="">— No Parent —</option>';

                const filtered = (parents || []).filter(p => {
                    const isPostable = normalizeBool(p.is_postable, true);
                    return p.type === currentType && isPostable === false;
                });

                filtered.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = `${p.code} — ${p.name}`;
                    parentSelect.appendChild(opt);
                });

                if (parentOld) {
                    parentSelect.value = parentOld;
                }

                if (parentSelect.value && !Array.from(parentSelect.options).some(o => o.value === parentSelect.value)) {
                    parentSelect.value = '';
                }
            }

            function syncNormalBalance() {
                normalBalance.value = calcNormalBalance(typeSelect.value);
            }

            typeSelect.addEventListener('change', function() {
                syncNormalBalance();
                renderParentOptions();
            });

            syncNormalBalance();
            renderParentOptions();
        });
    </script>
@endsection

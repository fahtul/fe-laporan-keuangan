@extends('finance.layout')
@section('title', 'Create Account')

@section('content')
    <div class="bg-white border rounded p-4 max-w-xl">
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
                @php $parentOld = old('parent_id', ''); @endphp
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
                {{-- ensure boolean always sent --}}
                <input type="hidden" name="is_postable" value="0">

                @php $postableOld = old('is_postable', 1); @endphp
                <label class="inline-flex items-center gap-2">
                    <input id="postableCheckbox" type="checkbox" name="is_postable" value="1" class="rounded"
                        {{ (string) $postableOld === '1' ? 'checked' : '' }}>
                    <span class="text-sm font-medium">Postable (bisa dipakai transaksi)</span>
                </label>

                <p class="text-xs text-gray-500 mt-1">
                    Matikan jika akun ini hanya header/grup.
                </p>
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
            const postableCheckbox = document.getElementById('postableCheckbox');

            // parents injected from backend
            // expected item shape: {id, code, name, type, is_postable}
            const parents = @json($parents ?? []);

            const parentOld = @json(old('parent_id', ''));
            let firstLoad = true;

            function calcNormalBalance(type) {
                return (type === 'asset' || type === 'expense') ? 'debit' : 'credit';
            }

            function normalizeBool(v, fallback = true) {
                // handle true/false, 1/0, "true"/"false"
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

                // keep the first option only
                parentSelect.innerHTML = '<option value="">— No Parent —</option>';

                // rule: only show parent candidates that match type and are NOT postable (header accounts)
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

                // restore selected on first load
                if (firstLoad && parentOld) {
                    parentSelect.value = parentOld;
                }

                // if selected parent doesn't exist after filtering, reset
                if (parentSelect.value && !Array.from(parentSelect.options).some(o => o.value === parentSelect
                        .value)) {
                    parentSelect.value = '';
                }

                syncPostableFromParent();
            }

            function syncNormalBalance() {
                normalBalance.value = calcNormalBalance(typeSelect.value);
            }

            // events
            typeSelect.addEventListener('change', function() {
                syncNormalBalance();
                renderParentOptions();
            });

            function syncPostableFromParent() {
                if (parentSelect.value) {
                    postableCheckbox.checked = true;
                } else {
                    postableCheckbox.checked = false;
                }
            }

            parentSelect.addEventListener('change', function() {
                syncPostableFromParent();
            });

            // init
            syncNormalBalance();
            renderParentOptions();
            syncPostableFromParent();
            firstLoad = false;
        });
    </script>
@endsection

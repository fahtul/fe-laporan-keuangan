@extends('finance.layout')

@section('title', 'Import Business Partners')
@section('subtitle', 'Import BP (Template / CSV / JSON)')

@section('header_actions')
    <a class="px-4 py-2 rounded border bg-white text-gray-900 hover:bg-gray-50"
        href="{{ route('finance.business_partners.index') }}">
        Back to BP
    </a>
@endsection

@section('content')
    @php
        $result = session('import_result');
        $result = is_array($result) ? $result : null;
        $parseErrors = is_array($result) ? (data_get($result, 'parse_errors') ?? null) : null;

        $created = data_get($result, 'created');
        $updated = data_get($result, 'updated');
        $skipped = data_get($result, 'skipped');
        $errors = data_get($result, 'errors');
    @endphp

    @if (session('import_error'))
        <div class="p-3 rounded bg-red-100 text-red-800">
            {{ session('import_error') }}
        </div>
    @endif

    @if ($result)
        <div class="bg-white border rounded p-4">
            <div class="font-semibold text-gray-900">Hasil Import</div>

            @if (!is_null($created) || !is_null($updated) || !is_null($skipped))
                <div class="mt-3 grid sm:grid-cols-3 gap-3">
                    <div class="border rounded p-3">
                        <div class="text-xs text-gray-500">Created</div>
                        <div class="text-xl font-semibold">{{ is_null($created) ? '-' : $created }}</div>
                    </div>
                    <div class="border rounded p-3">
                        <div class="text-xs text-gray-500">Updated</div>
                        <div class="text-xl font-semibold">{{ is_null($updated) ? '-' : $updated }}</div>
                    </div>
                    <div class="border rounded p-3">
                        <div class="text-xs text-gray-500">Skipped</div>
                        <div class="text-xl font-semibold">{{ is_null($skipped) ? '-' : $skipped }}</div>
                    </div>
                </div>
            @endif

            @if (is_array($parseErrors) && count($parseErrors) > 0)
                <div class="mt-4 p-3 rounded bg-yellow-50 border border-yellow-200 text-yellow-900">
                    <div class="font-semibold">CSV Parse Errors</div>
                    <ul class="list-disc ml-5 mt-2 text-sm">
                        @foreach ($parseErrors as $e)
                            <li>
                                Line {{ data_get($e, 'line', '-') }}: {{ data_get($e, 'message', 'Invalid row') }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (is_array($errors) && count($errors) > 0)
                <div class="mt-4 p-3 rounded bg-yellow-50 border border-yellow-200 text-yellow-900">
                    <div class="font-semibold">Errors</div>
                    <ul class="list-disc ml-5 mt-2 text-sm">
                        @foreach ($errors as $e)
                            <li>{{ is_string($e) ? $e : json_encode($e) }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <details class="mt-4">
                <summary class="cursor-pointer text-sm text-gray-600">Show raw response</summary>
                <pre class="mt-2 p-3 rounded bg-gray-50 border text-xs overflow-auto">{{ json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </details>
        </div>
    @endif

    <div class="grid lg:grid-cols-3 gap-4">
        <div class="bg-white border rounded p-4">
            <div class="font-semibold text-gray-900">Import Template</div>
            <p class="text-sm text-gray-600 mt-1">Backend akan generate BP dari template.</p>

            <div class="mt-3 flex gap-2 flex-wrap">
                <a class="px-3 py-2 rounded border bg-white text-gray-900 hover:bg-gray-50"
                    href="{{ route('finance.business-partners.import.template') }}">
                    Download hospital_bp_v1.csv
                </a>
            </div>

            <form method="POST" action="{{ route('finance.business-partners.import.store') }}" class="mt-3 space-y-3">
                @csrf
                <input type="hidden" name="source" value="template">

                <div>
                    <label class="block text-sm mb-1">Mode</label>
                    @php $modeOld = (string) old('mode', 'upsert'); @endphp
                    <select name="mode" class="border rounded p-2 w-full">
                        <option value="upsert" {{ $modeOld === 'upsert' ? 'selected' : '' }}>upsert</option>
                        <option value="insert_only" {{ $modeOld === 'insert_only' ? 'selected' : '' }}>insert_only</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm mb-1">Template</label>
                    <input readonly class="border rounded px-3 py-2 w-full bg-gray-100" value="hospital_bp_v1">
                </div>

                <button class="px-4 py-2 rounded bg-indigo-600 text-white hover:bg-indigo-700">
                    Import Template
                </button>
            </form>
        </div>

        <div class="bg-white border rounded p-4">
            <div class="font-semibold text-gray-900">Import CSV</div>
            <p class="text-sm text-gray-600 mt-1">Upload CSV dengan header kolom yang sesuai.</p>

            <div class="mt-3 p-3 rounded border bg-gray-50 text-xs">
                <div class="font-semibold mb-1">CSV Header</div>
                <div class="font-mono">code,name,category,normal_balance,is_active</div>
            </div>

            <form method="POST" action="{{ route('finance.business-partners.import.store') }}" class="mt-3 space-y-3"
                enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="source" value="csv">

                <div>
                    <label class="block text-sm mb-1">Mode</label>
                    @php $modeOld2 = (string) old('mode', 'upsert'); @endphp
                    <select name="mode" class="border rounded p-2 w-full">
                        <option value="upsert" {{ $modeOld2 === 'upsert' ? 'selected' : '' }}>upsert</option>
                        <option value="insert_only" {{ $modeOld2 === 'insert_only' ? 'selected' : '' }}>insert_only</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm mb-1">CSV File</label>
                    <input type="file" name="csv_file" accept=".csv,.txt" class="border rounded px-3 py-2 w-full"
                        required>
                </div>

                <button class="px-4 py-2 rounded bg-indigo-600 text-white hover:bg-indigo-700">
                    Import CSV
                </button>
            </form>
        </div>

        <div class="bg-white border rounded p-4">
            <div class="font-semibold text-gray-900">Import JSON</div>
            <p class="text-sm text-gray-600 mt-1">Paste array JSON atau object dengan key <span
                    class="font-mono">business_partners</span>.</p>

            <form method="POST" action="{{ route('finance.business-partners.import.store') }}" class="mt-3 space-y-3">
                @csrf
                <input type="hidden" name="source" value="json">

                <div>
                    <label class="block text-sm mb-1">Mode</label>
                    @php $modeOld3 = (string) old('mode', 'upsert'); @endphp
                    <select name="mode" class="border rounded p-2 w-full">
                        <option value="upsert" {{ $modeOld3 === 'upsert' ? 'selected' : '' }}>upsert</option>
                        <option value="insert_only" {{ $modeOld3 === 'insert_only' ? 'selected' : '' }}>insert_only</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm mb-1">JSON</label>
                    <textarea name="json_text" rows="10" class="border rounded px-3 py-2 w-full font-mono text-xs"
                        placeholder='{"business_partners":[{"code":"BP-01","name":"Supplier A","category":"supplier","normal_balance":"credit","is_active":true}] }'>{{ old('json_text') }}</textarea>
                </div>

                <button class="px-4 py-2 rounded bg-indigo-600 text-white hover:bg-indigo-700">
                    Import JSON
                </button>
            </form>
        </div>
    </div>
@endsection


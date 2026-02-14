<?php

namespace App\Http\Controllers\Finance;

use App\Helpers\FinanceApiHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AccountsImportController extends Controller
{
    private const PL_CATEGORIES = [
        'revenue',
        'cogs',
        'opex',
        'depreciation_amortization',
        'non_operating',
        'other',
    ];

    public function index()
    {
        return view('finance.accounts.import');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'mode' => 'nullable|in:upsert,insert_only',
            'source' => 'required|in:template,csv,json',
            'csv_file' => 'nullable|file|mimes:csv,txt',
            'json_text' => 'nullable|string',
        ]);

        $mode = (string) ($validated['mode'] ?? 'upsert');
        $source = (string) $validated['source'];

        if ($source === 'template') {
            return $this->importFromTemplate($mode);
        }

        if ($source === 'csv') {
            if (!$request->hasFile('csv_file')) {
                return back()->with('import_error', 'CSV file wajib diupload.')->withInput();
            }

            [$accounts, $parseErrors] = $this->parseCsvAccounts($request->file('csv_file'));
            if (!empty($parseErrors)) {
                return back()
                    ->with('import_error', 'CSV tidak valid. Periksa format dan data.')
                    ->with('import_result', ['parse_errors' => $parseErrors])
                    ->withInput();
            }

            return $this->importAccounts($mode, $accounts);
        }

        // json
        $jsonText = (string) ($validated['json_text'] ?? '');
        if (trim($jsonText) === '') {
            return back()->with('import_error', 'JSON text wajib diisi.')->withInput();
        }

        [$accounts, $jsonError] = $this->parseJsonAccounts($jsonText);
        if ($jsonError !== null) {
            return back()->with('import_error', $jsonError)->withInput();
        }

        return $this->importAccounts($mode, $accounts);
    }

    public function downloadHospitalTemplate()
    {
        $csv = implode("\n", [
            'code,name,type,parent_code,is_postable,cash_flow_category,requires_bp,subledger,pl_category',
            '1101,Kas,asset,,1,cash,0,,',
            '1120,Bank,asset,,1,cash,0,,',
            '2101,Hutang Usaha,liability,,1,,1,ap,',
            '1200,Piutang Usaha,asset,,1,,1,ar,',
            '3101,Modal,equity,,1,,0,,',
            '4101,Pendapatan Jasa,revenue,,1,,0,,revenue',
            '5101,HPP Obat,expense,,1,,0,,cogs',
            '5201,Beban Operasional,expense,,1,,0,,opex',
            '5301,Beban Penyusutan,expense,,1,,0,,depreciation_amortization',
        ]) . "\n";

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'hospital_v1_accounts_template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function importFromTemplate(string $mode)
    {
        $payload = [
            'mode' => $mode,
            'template' => 'hospital_v1',
        ];

        $res = FinanceApiHelper::post('/v1/accounts/import', $payload);

        return $this->handleImportResponse($res);
    }

    private function importAccounts(string $mode, array $accounts)
    {
        if (empty($accounts)) {
            return back()->with('import_error', 'Tidak ada data accounts untuk diimport.')->withInput();
        }

        $payload = [
            'mode' => $mode,
            'accounts' => $accounts,
        ];

        $res = FinanceApiHelper::post('/v1/accounts/import', $payload);

        return $this->handleImportResponse($res);
    }

    private function handleImportResponse(array $res)
    {
        if (!($res['success'] ?? false)) {
            $msg = (string) ($res['message'] ?? 'Gagal import accounts');
            $errors = $res['errors'] ?? null;

            if (!empty($errors) && is_array($errors)) {
                $msg .= ' (' . json_encode($errors) . ')';
            }

            return back()->with('import_error', $msg)->withInput();
        }

        $json = $res['data'] ?? null;
        $payload = $json;
        if (is_array($json)) {
            $payload = data_get($json, 'data.data') ?? data_get($json, 'data') ?? $json;
        }

        return back()
            ->with('import_result', $payload)
            ->with('success', 'Import COA berhasil diproses');
    }

    private function parseJsonAccounts(string $jsonText): array
    {
        try {
            $decoded = json_decode($jsonText, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return [[], 'JSON tidak valid: ' . $e->getMessage()];
        }

        $accounts = $decoded;
        if (is_array($decoded) && array_key_exists('accounts', $decoded)) {
            $accounts = $decoded['accounts'];
        }

        if (!is_array($accounts)) {
            return [[], 'JSON harus berupa array accounts atau object dengan key "accounts".'];
        }

        $normalized = [];
        foreach ($accounts as $idx => $row) {
            if (!is_array($row)) {
                return [[], 'Row JSON index ' . $idx . ' harus object/associative array.'];
            }

            $code = trim((string) data_get($row, 'code', ''));
            $name = trim((string) data_get($row, 'name', ''));
            $type = trim((string) data_get($row, 'type', ''));
            $plCategoryRaw = data_get($row, 'pl_category');
            $plCategory = $this->normalizePlCategory($plCategoryRaw);

            if ($code === '' || $name === '' || $type === '') {
                return [[], 'Row JSON index ' . $idx . ' wajib punya code, name, type.'];
            }
            if ($plCategoryRaw !== null && trim((string) $plCategoryRaw) !== '' && $plCategory === null) {
                return [[], 'Row JSON index ' . $idx . ' punya pl_category tidak valid. Gunakan: ' . implode(',', self::PL_CATEGORIES)];
            }

            $normalized[] = [
                'code' => $code,
                'name' => $name,
                'type' => $type,
                'parent_code' => $this->emptyToNull((string) data_get($row, 'parent_code', '')),
                'is_postable' => $this->normalizeBool(data_get($row, 'is_postable', true), true),
                'cash_flow_category' => $this->emptyToNull((string) data_get($row, 'cash_flow_category', '')),
                'requires_bp' => $this->normalizeBool(data_get($row, 'requires_bp', false), false),
                'subledger' => $this->emptyToNull((string) data_get($row, 'subledger', '')),
                'pl_category' => $plCategory,
            ];
        }

        return [$normalized, null];
    }

    private function parseCsvAccounts($uploadedFile): array
    {
        $path = method_exists($uploadedFile, 'getRealPath') ? $uploadedFile->getRealPath() : null;
        if (!$path || !is_readable($path)) {
            return [[], [['line' => 0, 'message' => 'CSV file tidak bisa dibaca.']]];
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [[], [['line' => 0, 'message' => 'CSV file gagal dibuka.']]];
        }

        $errors = [];
        $accounts = [];

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return [[], [['line' => 1, 'message' => 'CSV kosong.']]];
        }

        $header = array_map(function ($h) {
            $h = (string) $h;
            $h = preg_replace('/^\xEF\xBB\xBF/', '', $h); // remove UTF-8 BOM
            return strtolower(trim($h));
        }, $header);

        $requiredCols = ['code', 'name', 'type'];
        foreach ($requiredCols as $col) {
            if (!in_array($col, $header, true)) {
                $errors[] = ['line' => 1, 'message' => "Header wajib punya kolom: {$col}"];
            }
        }

        if (!empty($errors)) {
            fclose($handle);
            return [[], $errors];
        }

        $index = array_flip($header);

        $lineNo = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $lineNo++;

            $row = is_array($row) ? $row : [];
            $rowValues = array_map(fn($v) => is_string($v) ? trim($v) : (string) $v, $row);

            $isEmpty = true;
            foreach ($rowValues as $v) {
                if (trim((string) $v) !== '') {
                    $isEmpty = false;
                    break;
                }
            }
            if ($isEmpty) {
                continue;
            }

            $code = trim((string) ($rowValues[$index['code']] ?? ''));
            $name = trim((string) ($rowValues[$index['name']] ?? ''));
            $type = trim((string) ($rowValues[$index['type']] ?? ''));

            if ($code === '' || $name === '' || $type === '') {
                $errors[] = ['line' => $lineNo, 'message' => 'code, name, type wajib diisi'];
                continue;
            }

            $parentCode = isset($index['parent_code']) ? (string) ($rowValues[$index['parent_code']] ?? '') : '';
            $isPostableRaw = isset($index['is_postable']) ? ($rowValues[$index['is_postable']] ?? '1') : '1';
            $cashFlowCategory = isset($index['cash_flow_category']) ? (string) ($rowValues[$index['cash_flow_category']] ?? '') : '';
            $requiresBpRaw = isset($index['requires_bp']) ? ($rowValues[$index['requires_bp']] ?? '0') : '0';
            $subledger = isset($index['subledger']) ? (string) ($rowValues[$index['subledger']] ?? '') : '';
            $plCategoryRaw = isset($index['pl_category']) ? (string) ($rowValues[$index['pl_category']] ?? '') : '';

            $plCategory = $this->normalizePlCategory($plCategoryRaw);
            if (!is_null($plCategoryRaw) && trim($plCategoryRaw) !== '' && $plCategory === null) {
                $errors[] = [
                    'line' => $lineNo,
                    'message' => 'pl_category tidak valid. Gunakan: ' . implode(',', self::PL_CATEGORIES),
                ];
                continue;
            }

            $accounts[] = [
                'code' => $code,
                'name' => $name,
                'type' => $type,
                'parent_code' => $this->emptyToNull($parentCode),
                'is_postable' => $this->normalizeBool($isPostableRaw, true),
                'cash_flow_category' => $this->emptyToNull($cashFlowCategory),
                'requires_bp' => $this->normalizeBool($requiresBpRaw, false),
                'subledger' => $this->emptyToNull($subledger),
                'pl_category' => $plCategory,
            ];
        }

        fclose($handle);

        if (!empty($errors)) {
            return [[], $errors];
        }

        return [$accounts, []];
    }

    private function emptyToNull(string $value): ?string
    {
        $v = trim($value);
        return $v === '' ? null : $v;
    }

    private function normalizeBool($value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_float($value)) {
            return ((int) $value) === 1;
        }

        $v = strtolower(trim((string) $value));
        if ($v === '') {
            return $default;
        }

        if (in_array($v, ['1', 'true', 'yes', 'y', 'on'], true)) {
            return true;
        }

        if (in_array($v, ['0', 'false', 'no', 'n', 'off'], true)) {
            return false;
        }

        return $default;
    }

    private function normalizePlCategory($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $v = strtolower(trim((string) $value));
        if ($v === '') {
            return null;
        }

        return in_array($v, self::PL_CATEGORIES, true) ? $v : null;
    }
}

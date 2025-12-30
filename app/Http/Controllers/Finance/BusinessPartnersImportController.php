<?php

namespace App\Http\Controllers\Finance;

use App\Helpers\FinanceApiHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BusinessPartnersImportController extends Controller
{
    public function index()
    {
        return view('finance.business-partners.import');
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
            $payload = [
                'mode' => $mode,
                'source' => 'template',
                'template' => 'hospital_bp_v1',
            ];

            $res = FinanceApiHelper::post('/v1/business-partners/import', $payload);
            return $this->handleImportResponse($res);
        }

        if ($source === 'csv') {
            if (!$request->hasFile('csv_file')) {
                return back()->with('import_error', 'CSV file wajib diupload.')->withInput();
            }

            [$bps, $parseErrors] = $this->parseCsvBusinessPartners($request->file('csv_file'));
            if (!empty($parseErrors)) {
                return back()
                    ->with('import_error', 'CSV tidak valid. Periksa format dan data.')
                    ->with('import_result', ['parse_errors' => $parseErrors])
                    ->withInput();
            }

            if (empty($bps)) {
                return back()->with('import_error', 'Tidak ada data BP untuk diimport.')->withInput();
            }

            $payload = [
                'mode' => $mode,
                'source' => 'json',
                'business_partners' => $bps,
            ];

            $res = FinanceApiHelper::post('/v1/business-partners/import', $payload);
            return $this->handleImportResponse($res);
        }

        // json
        $jsonText = (string) ($validated['json_text'] ?? '');
        if (trim($jsonText) === '') {
            return back()->with('import_error', 'JSON text wajib diisi.')->withInput();
        }

        [$bps, $jsonError] = $this->parseJsonBusinessPartners($jsonText);
        if ($jsonError !== null) {
            return back()
                ->with('import_error', $jsonError)
                ->withInput();
        }

        if (empty($bps)) {
            return back()->with('import_error', 'Tidak ada data BP untuk diimport.')->withInput();
        }

        $payload = [
            'mode' => $mode,
            'source' => 'json',
            'business_partners' => $bps,
        ];

        $res = FinanceApiHelper::post('/v1/business-partners/import', $payload);
        return $this->handleImportResponse($res);
    }

    public function downloadHospitalTemplate()
    {
        $csv = implode("\n", [
            'code,name,category,normal_balance,is_active',
            'BP-001,RS Contoh Customer,customer,debit,1',
            'BP-002,Supplier Obat,vendor,credit,1',
            'BP-003,Asuransi ABC,insurance,debit,1',
            'BP-004,Lainnya,other,debit,1',
        ]) . "\n";

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'hospital_bp_v1.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function handleImportResponse(array $res)
    {
        if (!($res['success'] ?? false)) {
            $msg = (string) ($res['message'] ?? 'Gagal import business partners');
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
            ->with('success', 'Import BP berhasil diproses');
    }

    private function parseJsonBusinessPartners(string $jsonText): array
    {
        try {
            $decoded = json_decode($jsonText, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return [[], 'JSON tidak valid: ' . $e->getMessage()];
        }

        $bps = $decoded;
        if (is_array($decoded) && array_key_exists('business_partners', $decoded)) {
            $bps = $decoded['business_partners'];
        }

        if (!is_array($bps)) {
            return [[], 'JSON harus berupa array BP atau object dengan key "business_partners".'];
        }

        try {
            $normalized = $this->normalizeBusinessPartnersArray($bps);
        } catch (\Throwable $e) {
            return [[], $e->getMessage()];
        }

        return [$normalized, null];
    }

    private function parseCsvBusinessPartners($uploadedFile): array
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
        $bps = [];

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

        $requiredCols = ['code', 'name', 'category', 'normal_balance', 'is_active'];
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
            $rowValues = array_map(fn ($v) => is_string($v) ? trim($v) : (string) $v, $row);

            $isEmpty = true;
            foreach ($rowValues as $v) {
                if (trim((string) $v) !== '') {
                    $isEmpty = false;
                    break;
                }
            }
            if ($isEmpty) continue;

            $vals = [
                'code' => (string) ($rowValues[$index['code']] ?? ''),
                'name' => (string) ($rowValues[$index['name']] ?? ''),
                'category' => (string) ($rowValues[$index['category']] ?? ''),
                'normal_balance' => (string) ($rowValues[$index['normal_balance']] ?? ''),
                'is_active' => $rowValues[$index['is_active']] ?? null,
            ];

            try {
                $bp = $this->normalizeBusinessPartnerRow($vals);
                if ($bp) $bps[] = $bp;
            } catch (\Throwable $e) {
                $errors[] = ['line' => $lineNo, 'message' => $e->getMessage()];
            }
        }

        fclose($handle);

        if (!empty($errors)) {
            return [[], $errors];
        }

        return [$bps, []];
    }

    private function normalizeBusinessPartnersArray(array $bps): array
    {
        $rows = [];
        foreach ($bps as $idx => $it) {
            if (!is_array($it)) {
                throw new \RuntimeException('Row JSON index ' . $idx . ' harus object/associative array.');
            }

            $vals = [
                'code' => trim((string) ($it['code'] ?? '')),
                'name' => trim((string) ($it['name'] ?? '')),
                'category' => trim((string) ($it['category'] ?? '')),
                'normal_balance' => trim((string) ($it['normal_balance'] ?? '')),
                'is_active' => array_key_exists('is_active', $it) ? $it['is_active'] : null,
            ];

            $bp = $this->normalizeBusinessPartnerRow($vals);
            if ($bp) $rows[] = $bp;
        }

        return $rows;
    }

    private function normalizeBusinessPartnerRow(array $vals): ?array
    {
        $code = strtoupper(trim((string) ($vals['code'] ?? '')));
        $name = trim((string) ($vals['name'] ?? ''));

        $categoryRaw = trim((string) ($vals['category'] ?? ''));
        $category = $this->normalizeCategory($categoryRaw !== '' ? $categoryRaw : 'other');

        $normalBalanceRaw = strtolower(trim((string) ($vals['normal_balance'] ?? '')));
        $normalBalance = $normalBalanceRaw !== '' ? $this->normalizeNormalBalance($normalBalanceRaw) : null;

        $isActiveRaw = $vals['is_active'] ?? null;
        $isActive = $this->parseBoolOrNull($isActiveRaw);

        if ($code === '' && $name === '') {
            return null;
        }

        if ($code === '') {
            throw new \RuntimeException('Kolom code wajib diisi.');
        }
        if ($name === '') {
            throw new \RuntimeException("BP {$code}: kolom name wajib diisi.");
        }

        $bp = [
            'code' => $code,
            'name' => $name,
            'category' => $category,
        ];

        if ($normalBalance !== null) {
            $bp['normal_balance'] = $normalBalance;
        }

        if ($isActive !== null) {
            $bp['is_active'] = $isActive;
        }

        return $bp;
    }

    private function normalizeCategory(string $category): string
    {
        $c = strtolower(trim($category));
        if ($c === 'vendor') $c = 'supplier';
        if ($c === 'insurance') $c = 'insurer';

        $aliases = [
            'customers' => 'customer',
            'suppliers' => 'supplier',
            'insurances' => 'insurer',
        ];
        if (array_key_exists($c, $aliases)) {
            $c = $aliases[$c];
        }

        if (!in_array($c, ['customer', 'supplier', 'insurer', 'other'], true)) {
            throw new \RuntimeException("Category tidak dikenali: {$category}. Gunakan customer/supplier/insurer/other.");
        }

        return $c;
    }

    private function normalizeNormalBalance(string $v): string
    {
        if (!in_array($v, ['debit', 'credit'], true)) {
            throw new \RuntimeException("normal_balance tidak valid: {$v}. Gunakan debit/credit.");
        }

        return $v;
    }

    private function parseBoolOrNull($v): ?bool
    {
        if ($v === null) return null;
        if (is_bool($v)) return $v;

        if (is_int($v) || is_float($v)) {
            return ((int) $v) === 1;
        }

        $s = strtolower(trim((string) $v));
        if ($s === '') return null;
        if (in_array($s, ['1', 'true', 'yes', 'y'], true)) return true;
        if (in_array($s, ['0', 'false', 'no', 'n'], true)) return false;

        return null;
    }
}


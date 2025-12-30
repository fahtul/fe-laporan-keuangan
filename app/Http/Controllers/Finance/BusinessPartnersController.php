<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Helpers\FinanceApiHelper;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class BusinessPartnersController extends Controller
{
    private function categoryOptions(): array
    {
        return [
            'customer' => 'Customer',
            'supplier' => 'Supplier',
            'insurer' => 'Asuransi',
            'other' => 'Lainnya',
        ];
    }

    private function normalBalanceOptions(): array
    {
        return [
            'debit' => 'Debet',
            'credit' => 'Kredit',
        ];
    }

    private function enrichCategoryOptionsForEdit(?string $currentCategory): array
    {
        $opts = $this->categoryOptions();

        $currentCategory = $currentCategory !== null ? trim($currentCategory) : null;
        if ($currentCategory && !array_key_exists($currentCategory, $opts)) {
            $opts = [$currentCategory => "Legacy: {$currentCategory}"] + $opts;
        }

        return $opts;
    }

    public function options(Request $request)
    {
        $category = trim((string) $request->query('category', ''));
        $q = trim((string) $request->query('q', ''));
        $limit = (int) $request->query('limit', 100);

        $params = [
            'q' => $q,
            'limit' => $limit > 0 ? $limit : 100,
        ];

        if ($category !== '') {
            $params['category'] = $category;
        }

        $res = FinanceApiHelper::get('/v1/business-partners/options', $params);

        if (!($res['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $res['message'] ?? 'Failed to load options',
                'data' => [],
            ]);
        }

        $json = $res['data'] ?? null;
        $payload = data_get($json, 'data.data') ?? data_get($json, 'data') ?? [];
        $items = is_array($payload) ? $payload : [];

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function index(Request $request)
    {
        $q = (string) $request->query('q', '');
        $category = (string) $request->query('category', '');
        $includeInactive = $request->boolean('include_inactive');

        $page = (int) $request->query('page', 1);
        $limit = (int) $request->query('limit', 20);

        $params = [
            'q' => $q,
            // backend cenderung pakai flag numeric/string, jadi kirim tegas 1/0
            'include_inactive' => $includeInactive ? '1' : '0',
            'page' => $page,
            'limit' => $limit,
        ];

        // backend baru: filter pakai `category`, backend lama: mungkin masih pakai `role`
        if (trim($category) !== '') {
            $params['category'] = $category;
            $params['role'] = $category;
        }

        $res = FinanceApiHelper::get('/v1/business-partners', $params);

        $items = data_get($res, 'data.data', []);
        $meta  = data_get($res, 'data.meta', [
            'total' => 0,
            'page' => $page,
            'limit' => $limit,
        ]);

        $paginator = new LengthAwarePaginator(
            $items,
            (int) data_get($meta, 'total', 0),
            (int) data_get($meta, 'limit', $limit),
            (int) data_get($meta, 'page', $page),
            [
                'path' => route('finance.business_partners.index'),
                'query' => $request->query(),
            ]
        );

        return view('finance.business_partners.index', [
            'items' => $paginator,
            'q' => $q,
            'category' => $category,
            'include_inactive' => $includeInactive,
            'limit' => $limit,
            'categoryOptions' => $this->categoryOptions(),
            'normalBalanceOptions' => $this->normalBalanceOptions(),
            'apiError' => ($res['success'] ?? false) ? null : ($res['message'] ?? 'Failed'),
        ]);
    }

    public function create()
    {
        return view('finance.business_partners.create', [
            'categoryOptions' => $this->categoryOptions(),
            'normalBalanceOptions' => $this->normalBalanceOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $payload = $request->validate([
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:200',
            'category' => 'nullable|in:customer,supplier,insurer,other,vendor,insurance',
            'normal_balance' => 'nullable|in:debit,credit',
            'is_active' => 'nullable|boolean',
        ]);

        $category = (string) ($payload['category'] ?? '');
        if ($category === 'vendor') $category = 'supplier';
        if ($category === 'insurance') $category = 'insurer';
        $payload['category'] = $category !== '' ? $category : null;

        // checkbox handling
        $payload['is_active'] = $request->has('is_active');

        $res = FinanceApiHelper::post('/v1/business-partners', $payload);

        if (!($res['success'] ?? false)) {
            $errorCode = $res['error_code'] ?? data_get($res, 'data.error_code');

            $errUp = is_string($errorCode) ? strtoupper($errorCode) : '';
            $isDup =
                ($errUp !== '' && str_contains($errUp, 'DUPLICATE'))
                || ($errUp !== '' && str_contains($errUp, 'CODE') && (str_contains($errUp, 'EXIST') || str_contains($errUp, 'UNIQUE')));

            if ($isDup) {
                return back()
                    ->withErrors(['code' => 'Code sudah dipakai. Gunakan code lain.'])
                    ->withInput();
            }

            $msg = (string) ($res['message'] ?? 'Gagal menyimpan');
            if (str_contains(strtolower($msg), 'code') && (str_contains(strtolower($msg), 'exist') || str_contains(strtolower($msg), 'unique') || str_contains(strtolower($msg), 'duplicate'))) {
                return back()
                    ->withErrors(['code' => $msg])
                    ->withInput();
            }

            // handle soft-deleted hint (dari BE handler)
            $restoreData = data_get($res, 'data.data');

            return back()
                ->withErrors(['api' => $res['message'] ?? 'Gagal menyimpan'])
                ->withInput()
                ->with('bp_error_code', $errorCode)
                ->with('bp_restore_data', $restoreData);
        }

        $id = data_get($res, 'data.data.id');
        return redirect()
            ->route('finance.business_partners.edit', $id)
            ->with('success', 'Business Partner berhasil dibuat');
    }

    public function edit(string $id)
    {
        $res = FinanceApiHelper::get("/v1/business-partners/{$id}");
        if (!($res['success'] ?? false)) {
            abort(500, $res['message'] ?? 'Failed');
        }

        $item = data_get($res, 'data.data', []);
        return view('finance.business_partners.edit', [
            'item' => $item,
            'categoryOptions' => $this->enrichCategoryOptionsForEdit(data_get($item, 'category')),
            'normalBalanceOptions' => $this->normalBalanceOptions(),
        ]);
    }

    public function update(string $id, Request $request)
    {
        $payload = $request->validate([
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:200',
            'category' => 'nullable|in:customer,supplier,insurer,other,vendor,insurance',
            'normal_balance' => 'nullable|in:debit,credit',
            'is_active' => 'nullable|boolean',
        ]);

        $category = (string) ($payload['category'] ?? '');
        if ($category === 'vendor') $category = 'supplier';
        if ($category === 'insurance') $category = 'insurer';
        $payload['category'] = $category !== '' ? $category : null;

        $payload['is_active'] = $request->has('is_active');

        $res = FinanceApiHelper::put("/v1/business-partners/{$id}", $payload);

        if (!($res['success'] ?? false)) {
            $errorCode = $res['error_code'] ?? data_get($res, 'data.error_code');

            $errUp = is_string($errorCode) ? strtoupper($errorCode) : '';
            $isDup =
                ($errUp !== '' && str_contains($errUp, 'DUPLICATE'))
                || ($errUp !== '' && str_contains($errUp, 'CODE') && (str_contains($errUp, 'EXIST') || str_contains($errUp, 'UNIQUE')));

            if ($isDup) {
                return back()
                    ->withErrors(['code' => 'Code sudah dipakai. Gunakan code lain.'])
                    ->withInput();
            }

            $msg = (string) ($res['message'] ?? 'Gagal update');
            if (str_contains(strtolower($msg), 'code') && (str_contains(strtolower($msg), 'exist') || str_contains(strtolower($msg), 'unique') || str_contains(strtolower($msg), 'duplicate'))) {
                return back()
                    ->withErrors(['code' => $msg])
                    ->withInput();
            }

            return back()
                ->withErrors(['api' => $res['message'] ?? 'Gagal update'])
                ->withInput();
        }

        return redirect()
            ->route('finance.business_partners.edit', $id)
            ->with('success', 'Business Partner berhasil diupdate');
    }

    public function destroy(string $id)
    {
        $res = FinanceApiHelper::delete("/v1/business-partners/{$id}");

        if (!($res['success'] ?? false)) {
            return back()->withErrors(['api' => $res['message'] ?? 'Gagal hapus']);
        }

        return redirect()
            ->route('finance.business_partners.index')
            ->with('success', 'Business Partner berhasil dihapus (soft delete)');
    }

    public function restore(string $id)
    {
        $res = FinanceApiHelper::post("/v1/business-partners/{$id}/restore", []);

        if (!($res['success'] ?? false)) {
            return back()->withErrors(['api' => $res['message'] ?? 'Gagal restore']);
        }

        return redirect()
            ->route('finance.business_partners.edit', $id)
            ->with('success', 'Business Partner berhasil direstore');
    }
}

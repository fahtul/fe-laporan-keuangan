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
            'customer' => 'Customer / Penjamin',
            'supplier' => 'Supplier',
            'patient' => 'Pasien',
            'doctor' => 'Dokter',
            'insurer' => 'Asuransi',
            'employee' => 'Karyawan',
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

    public function index(Request $request)
    {
        $q = (string) $request->query('q', '');
        $category = (string) $request->query('category', '');
        $includeInactive = (string) $request->query('include_inactive', 'false');

        $page = (int) $request->query('page', 1);
        $limit = (int) $request->query('limit', 20);

        $res = FinanceApiHelper::get('/v1/business-partners', [
            'q' => $q,
            'role' => $category, // backend handler pakai query "role"
            'include_inactive' => $includeInactive,
            'page' => $page,
            'limit' => $limit,
        ]);

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
            'category' => 'nullable|string|max:50',
            'normal_balance' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        // checkbox handling
        $payload['is_active'] = $request->has('is_active');

        $res = FinanceApiHelper::post('/v1/business-partners', $payload);

        if (!($res['success'] ?? false)) {
            // handle soft-deleted hint (dari BE handler)
            $errorCode = data_get($res, 'data.error_code');
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
            'categoryOptions' => $this->categoryOptions(),
            'normalBalanceOptions' => $this->normalBalanceOptions(),
        ]);
    }

    public function update(string $id, Request $request)
    {
        $payload = $request->validate([
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:200',
            'category' => 'nullable|string|max:50',
            'normal_balance' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        $payload['is_active'] = $request->has('is_active');

        $res = FinanceApiHelper::put("/v1/business-partners/{$id}", $payload);

        if (!($res['success'] ?? false)) {
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

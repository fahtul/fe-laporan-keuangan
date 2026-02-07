<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Finance\AccountsController;
use App\Http\Controllers\Finance\BalanceSheetController;
use App\Http\Controllers\Finance\AccountsCashflowMappingController;
use App\Http\Controllers\Finance\BusinessPartnersController;
use App\Http\Controllers\Finance\BusinessPartnersImportController;
use App\Http\Controllers\Finance\CashFlowController;
use App\Http\Controllers\Finance\DashboardController;
use App\Http\Controllers\Finance\EquityStatementController;
use App\Http\Controllers\Finance\JournalEntriesController;
use App\Http\Controllers\Finance\OpeningBalancesController;
use App\Http\Controllers\Finance\LedgersController;
use App\Http\Controllers\Finance\IncomeStatementController;
use App\Http\Controllers\Finance\TrialBalanceController;
use App\Http\Controllers\Finance\SubledgersController;
use App\Http\Controllers\Finance\WorksheetController;
use App\Http\Controllers\Finance\ClosingsController;
use App\Http\Controllers\Finance\AccountsImportController;
use App\Http\Controllers\Finance\ReportExportsController;
use App\Http\Controllers\ProfileController;
use App\Services\Finance\FinanceApiClient;
use App\Services\Finance\FinanceBffToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

// Disable self-register: always redirect/404 even if someone guesses the URL.
Route::middleware('guest')->group(function () {
    Route::get('/register', function () {
        return redirect()->route('login');
    })->name('register');

    Route::post('/register', function () {
        abort(404);
    });
});

Route::get('/whoami', function () {
    return [
        'logged_in' => Auth::check(),
        'user' => Auth::user()?->only(['id', 'email', 'name']),
    ];
})->middleware('web');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/finance/me', function (FinanceApiClient $client) {
    $res = $client->get('/v1/me');

    return response()->json([
        'status_code' => $res->status(),
        'body' => $res->json(),
    ]);
})->middleware('auth');

Route::get('/finance/debug', function () {
    $user = Auth::user();

    return [
        'user_id' => (string) $user->id,
        'user_id_is_uuid' => Str::isUuid((string) $user->id),

        'finance_org_id' => config('finance.organization_id'),
        'finance_org_id_is_uuid' => Str::isUuid((string) config('finance.organization_id')),

        'finance_base_url' => config('finance.base_url'),
    ];
})->middleware('auth');

Route::get('/finance/rbac-test', function (FinanceApiClient $client) {
    $res = $client->get('/v1/rbac/admin-only');
    return response()->json([
        'status_code' => $res->status(),
        'body' => $res->json(),
    ]);
})->middleware('auth');

Route::get('/finance/audit-test', function (FinanceApiClient $client) {
    $res = $client->post('/v1/audit-test', []);
    return response()->json([
        'status_code' => $res->status(),
        'body' => $res->json(),
    ]);
})->middleware('auth');


Route::get('/finance/token-debug', function () {
    $token = FinanceBffToken::make(auth()->user());

    // decode payload JWT tanpa verify (buat debug)
    [, $payloadB64,] = explode('.', $token);
    $payloadJson = base64_decode(strtr($payloadB64, '-_', '+/'));
    $payload = json_decode($payloadJson, true);

    return [
        'ttl_config' => config('finance.ttl'),
        'iat' => $payload['iat'] ?? null,
        'exp' => $payload['exp'] ?? null,
        'now' => time(),
        'expires_in_sec' => isset($payload['exp']) ? ($payload['exp'] - time()) : null,
    ];
})->middleware('auth');

Route::get('/finance/token', function () {
    $token = FinanceBffToken::make(auth()->user());
    return response($token, 200)->header('Content-Type', 'text/plain');
})->middleware('auth');

Route::get('/finance/debug-role', function () {
    $u = auth()->user();
    return [
        'user_id' => $u->id,
        'email' => $u->email,
        'role' => $u->role ?? null,
    ];
})->middleware('auth');


Route::get('/finance/token-payload', function () {
    $token = FinanceBffToken::make(auth()->user());
    [$h, $p, $s] = explode('.', $token);

    $payload = json_decode(base64_decode(strtr($p, '-_', '+/')), true);

    return $payload;
})->middleware('auth');


Route::get('/finance/db-info', function () {
    return [
        'db_connection' => config('database.default'),
        'database' => DB::connection()->getDatabaseName(),
        'host' => config('database.connections.' . config('database.default') . '.host'),
    ];
})->middleware('auth');


Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'verified', 'role:admin'])
    ->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
        Route::patch('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset');
    });

Route::middleware(['auth', 'finance.access:admin,accountant,viewer'])
    ->prefix('finance')
    ->group(function () {
        Route::get('/accounts', [AccountsController::class, 'index'])->name('finance.accounts.index');

        Route::middleware('finance.access:admin,accountant')->group(function () {
            Route::get('/accounts/import', [AccountsImportController::class, 'index'])->name('finance.accounts.import.index');
            Route::post('/accounts/import', [AccountsImportController::class, 'store'])->name('finance.accounts.import.store');
            Route::get('/accounts/import/template/hospital_v1.csv', [AccountsImportController::class, 'downloadHospitalTemplate'])
                ->name('finance.accounts.import.template.hospital_v1');

            Route::get('/accounts/create', [AccountsController::class, 'create'])->name('finance.accounts.create');
            Route::post('/accounts', [AccountsController::class, 'store'])->name('finance.accounts.store');

            Route::get('/accounts/{id}/edit', [AccountsController::class, 'edit'])->name('finance.accounts.edit');
            Route::put('/accounts/{id}', [AccountsController::class, 'update'])->name('finance.accounts.update');

            Route::delete('/accounts/{id}', [AccountsController::class, 'destroy'])->name('finance.accounts.destroy');
            Route::post('/accounts/{id}/restore', [AccountsController::class, 'restore'])
                ->name('finance.accounts.restore');

            Route::get('/accounts/cashflow-mapping', [AccountsCashflowMappingController::class, 'index'])
                ->name('finance.accounts.cashflow_mapping.index');
            Route::post('/accounts/cashflow-mapping', [AccountsCashflowMappingController::class, 'store'])
                ->name('finance.accounts.cashflow_mapping.store');
        });
    });


Route::middleware(['auth', 'finance.access:admin,accountant,viewer'])
    ->prefix('finance')
    ->group(function () {

        // ===== Journal Entries (punyamu) =====
        Route::get('/journal-entries', [JournalEntriesController::class, 'index'])
            ->name('finance.journal_entries.index');

        Route::get('/journal-entries/{id}/edit', [JournalEntriesController::class, 'edit'])
            ->name('finance.journal_entries.edit');

        // ===== Business Partners (VIEW) =====
        Route::get('/business-partners', [BusinessPartnersController::class, 'index'])
            ->name('finance.business_partners.index');

        Route::get('/business-partners/options', [BusinessPartnersController::class, 'options'])
            ->name('finance.business_partners.options');

        Route::get('/business-partners/{id}/edit', [BusinessPartnersController::class, 'edit'])
            ->name('finance.business_partners.edit');

	        // ===== WRITE (admin/accountant saja) =====
	        Route::middleware('finance.access:admin,accountant')->group(function () {

            // Journal Entries (punyamu)
            Route::get('/journal-entries/create', [JournalEntriesController::class, 'create'])
                ->name('finance.journal_entries.create');

            Route::post('/journal-entries', [JournalEntriesController::class, 'store'])
                ->name('finance.journal_entries.store');

            Route::put('/journal-entries/{id}', [JournalEntriesController::class, 'update'])
                ->name('finance.journal_entries.update');

            Route::post('/journal-entries/{id}/post', [JournalEntriesController::class, 'post'])
                ->name('finance.journal_entries.post');

            Route::post('/journal-entries/{id}/reverse', [JournalEntriesController::class, 'reverse'])
                ->name('finance.journal_entries.reverse');

	            // Business Partners (WRITE)
	            Route::get('/business-partners/import', [BusinessPartnersImportController::class, 'index'])
	                ->name('finance.business-partners.import');
	            Route::post('/business-partners/import', [BusinessPartnersImportController::class, 'store'])
	                ->name('finance.business-partners.import.store');
	            Route::get('/business-partners/import/template/hospital_bp_v1.csv', [BusinessPartnersImportController::class, 'downloadHospitalTemplate'])
	                ->name('finance.business-partners.import.template');

	            Route::get('/business-partners/create', [BusinessPartnersController::class, 'create'])
	                ->name('finance.business_partners.create');

            Route::post('/business-partners', [BusinessPartnersController::class, 'store'])
                ->name('finance.business_partners.store');

            Route::put('/business-partners/{id}', [BusinessPartnersController::class, 'update'])
                ->name('finance.business_partners.update');

            Route::delete('/business-partners/{id}', [BusinessPartnersController::class, 'destroy'])
                ->name('finance.business_partners.destroy');

            Route::post('/business-partners/{id}/restore', [BusinessPartnersController::class, 'restore'])
                ->name('finance.business_partners.restore');
        });
    });

Route::middleware(['auth', 'finance.access:admin,accountant,viewer'])
    ->prefix('finance')
    ->group(function () {
        Route::get('/opening-balances', [OpeningBalancesController::class, 'index'])
            ->name('finance.opening_balances.index');

        Route::middleware('finance.access:admin,accountant')->group(function () {
            Route::get('/opening-balances/create', [OpeningBalancesController::class, 'create'])
                ->name('finance.opening_balances.create');

            Route::post('/opening-balances', [OpeningBalancesController::class, 'store'])
                ->name('finance.opening_balances.store');

            Route::put('/opening-balances/{id}', [OpeningBalancesController::class, 'update'])
                ->name('finance.opening_balances.update');
        });
    });

Route::middleware(['auth', 'finance.access:admin,accountant,viewer'])
    ->prefix('finance')
    ->group(function () {
        Route::get('/ledgers', [LedgersController::class, 'index'])
            ->name('finance.ledgers.index');
    });

Route::middleware(['auth', 'finance.access:admin,accountant,viewer'])
    ->prefix('finance')
    ->group(function () {
        Route::get('/trial-balance', [TrialBalanceController::class, 'index'])
            ->name('finance.trial_balance.index');
    });

Route::middleware(['auth', 'finance.access:admin,accountant,viewer'])
    ->prefix('finance')
    ->group(function () {
        Route::get('/worksheet', [WorksheetController::class, 'index'])
            ->name('finance.worksheet.index');
    });

Route::middleware(['auth', 'finance.access:admin,accountant,viewer'])
    ->prefix('finance')
    ->group(function () {
        Route::get('/equity-statement', [EquityStatementController::class, 'index'])
            ->name('finance.equity_statement.index');
    });

Route::middleware(['auth', 'finance.access:admin,accountant,viewer'])
    ->prefix('finance')
    ->group(function () {
        Route::get('/cash-flow', [CashFlowController::class, 'index'])
            ->name('finance.cash_flow.index');
    });

Route::middleware(['auth', 'finance.access:admin,accountant,viewer'])
    ->prefix('finance')
    ->group(function () {
        Route::get('/balance-sheet', [BalanceSheetController::class, 'index'])
            ->name('finance.balance_sheet.index');
    });

Route::middleware(['auth', 'finance.access:admin,accountant,viewer'])
    ->prefix('finance')
    ->group(function () {
        Route::get('/income-statement', [IncomeStatementController::class, 'index'])
            ->name('finance.income_statement.index');
    });

Route::middleware(['auth', 'finance.access:admin,accountant,viewer'])
    ->prefix('finance')
    ->group(function () {
        Route::get('/subledgers', [SubledgersController::class, 'index'])
            ->name('finance.subledgers.index');
        Route::get('/subledgers/{bpId}', [SubledgersController::class, 'show'])
            ->name('finance.subledgers.show');
    });

Route::middleware(['auth', 'finance.access:admin,accountant,viewer'])
    ->prefix('finance/closings')
    ->group(function () {
        Route::get('/year-end', [ClosingsController::class, 'index'])
            ->name('finance.closings.year_end.index');
        Route::post('/year-end', [ClosingsController::class, 'store'])
            ->middleware('finance.access:admin,accountant')
            ->name('finance.closings.year_end.store');
    });

Route::middleware(['auth', 'finance.access:admin,accountant,viewer'])
    ->prefix('finance')
    ->group(function () {
        Route::get('/exports/{report}.xlsx', [ReportExportsController::class, 'exportXlsx'])
            ->name('finance.exports.xlsx');
    });


require __DIR__ . '/auth.php';

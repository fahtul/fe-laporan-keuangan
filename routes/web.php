<?php

use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\Finance\AccountsController;
use App\Http\Controllers\Finance\BusinessPartnersController;
use App\Http\Controllers\Finance\JournalEntriesController;
use App\Http\Controllers\Finance\OpeningBalancesController;
use App\Http\Controllers\Finance\LedgersController;
use App\Http\Controllers\ProfileController;
use App\Services\Finance\FinanceApiClient;
use App\Services\Finance\FinanceBffToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/whoami', function () {
    return [
        'logged_in' => Auth::check(),
        'user' => Auth::user()?->only(['id', 'email', 'name']),
    ];
})->middleware('web');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

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


Route::middleware(['auth'])->prefix('admin')->group(function () {
    Route::get('/users', [UserAdminController::class, 'index'])->name('admin.users.index');

    Route::post('/users/{user}/toggle-active', [UserAdminController::class, 'toggleActive'])
        ->name('admin.users.toggleActive');
});

Route::middleware(['auth', 'finance.access:admin,accountant,viewer'])
    ->prefix('finance')
    ->group(function () {
        Route::get('/accounts', [AccountsController::class, 'index'])->name('finance.accounts.index');

        Route::middleware('finance.access:admin,accountant')->group(function () {
            Route::get('/accounts/create', [AccountsController::class, 'create'])->name('finance.accounts.create');
            Route::post('/accounts', [AccountsController::class, 'store'])->name('finance.accounts.store');

            Route::get('/accounts/{id}/edit', [AccountsController::class, 'edit'])->name('finance.accounts.edit');
            Route::put('/accounts/{id}', [AccountsController::class, 'update'])->name('finance.accounts.update');

            Route::delete('/accounts/{id}', [AccountsController::class, 'destroy'])->name('finance.accounts.destroy');
            Route::post('/accounts/{id}/restore', [AccountsController::class, 'restore'])
                ->name('finance.accounts.restore');
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
        });
    });

    Route::middleware(['auth', 'finance.access:admin,accountant,viewer'])
    ->prefix('finance')
    ->group(function () {
        Route::get('/ledgers', [LedgersController::class, 'index'])
            ->name('finance.ledgers.index');
    });
    

require __DIR__ . '/auth.php';

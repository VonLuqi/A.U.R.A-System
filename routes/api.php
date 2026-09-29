<?php

/**
 * API JSON (session cookie + CSRF).
 *
 * Loaded from routes/web.php with prefix `api` and the `web` middleware stack
 * (Etapa C §1.1 — Opção A: session-aware API without Sanctum / without the
 * default Laravel `api` middleware group, which is stateless).
 *
 * Inventory (Etapa C §5.1) — canonical names for Etapa D.
 */

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\StatementImportController;
use App\Http\Controllers\StatementUploadController;
use App\Http\Controllers\TransactionAliasController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

/*
| CSRF bootstrap for SPA (Etapa C §5.8) — no Sanctum.
| Any GET under the `web` stack also sets XSRF-TOKEN; this endpoint is the
| explicit pre-login handshake for Axios (`withCredentials` + X-XSRF-TOKEN).
*/
Route::get('/csrf-cookie', function (): Response {
    return response()->noContent();
})->name('api.csrf-cookie');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware(['guest', 'throttle:login'])
    ->name('api.login');

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('api.logout');

    Route::post('/statements/upload', [StatementUploadController::class, 'store'])
        ->middleware(['quota:uploads', 'throttle:statements-upload'])
        ->name('api.statements.upload');

    // §8 — read endpoints share a mild per-user throttle.
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/user', [AuthenticatedSessionController::class, 'show'])
            ->name('api.user');

        Route::get('/statements', [StatementImportController::class, 'index'])
            ->name('api.statements.index');

        Route::get('/statements/{statementImport}', [StatementImportController::class, 'show'])
            ->name('api.statements.show');

        Route::get('/analytics/dashboard', [AnalyticsController::class, 'dashboard'])
            ->name('api.analytics.dashboard');

        Route::get('/categories', [CategoryController::class, 'index'])
            ->name('api.categories.index');
        Route::post('/categories', [CategoryController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('api.categories.store');
    });

    // Transactions — list/detail + manual CRUD (PLAN_EXPANSAO §3.1 / §3.3)
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/transactions', [TransactionController::class, 'index'])
            ->name('api.transactions.index');

        Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])
            ->name('api.transactions.show');

        Route::match(['patch', 'put'], '/transactions/{transaction}', [TransactionController::class, 'update'])
            ->name('api.transactions.update');

        Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])
            ->name('api.transactions.destroy');
    });

    Route::post('/transactions', [TransactionController::class, 'store'])
        ->middleware(['quota:manual_transactions', 'throttle:60,1'])
        ->name('api.transactions.store');

    // Aliases / categorization rules (PLAN_EXPANSAO §4.2)
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/aliases', [TransactionAliasController::class, 'index'])
            ->name('api.aliases.index');
        Route::post('/aliases/preview', [TransactionAliasController::class, 'preview'])
            ->name('api.aliases.preview');
        Route::post('/aliases', [TransactionAliasController::class, 'store'])
            ->name('api.aliases.store');
        Route::get('/aliases/{alias}', [TransactionAliasController::class, 'show'])
            ->name('api.aliases.show');
        Route::patch('/aliases/{alias}', [TransactionAliasController::class, 'update'])
            ->name('api.aliases.update');
        Route::delete('/aliases/{alias}', [TransactionAliasController::class, 'destroy'])
            ->name('api.aliases.destroy');
        Route::post('/transactions/{transaction}/remember-alias', [TransactionAliasController::class, 'remember'])
            ->name('api.transactions.remember-alias');
    });

    // Goals (PLAN_EXPANSAO §7.2)
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/goals', [GoalController::class, 'index'])
            ->name('api.goals.index');
        Route::post('/goals', [GoalController::class, 'store'])
            ->name('api.goals.store');
        Route::get('/goals/{goal}', [GoalController::class, 'show'])
            ->name('api.goals.show');
        Route::patch('/goals/{goal}', [GoalController::class, 'update'])
            ->name('api.goals.update');
        Route::delete('/goals/{goal}', [GoalController::class, 'destroy'])
            ->name('api.goals.destroy');
        Route::post('/goals/{goal}/recalculate', [GoalController::class, 'recalculate'])
            ->name('api.goals.recalculate');
    });

    // Admin user management (PLAN_EXPANSAO §2.3)
    Route::middleware(['role:admin', 'throttle:30,1'])->group(function () {
        Route::get('/users', [UserController::class, 'index'])
            ->name('api.users.index');
        Route::post('/users', [UserController::class, 'store'])
            ->name('api.users.store');
        Route::get('/users/{user}', [UserController::class, 'show'])
            ->name('api.users.show');
        Route::patch('/users/{user}', [UserController::class, 'update'])
            ->name('api.users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])
            ->name('api.users.destroy');
    });
});

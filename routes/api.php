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
use App\Http\Controllers\StatementImportController;
use App\Http\Controllers\StatementUploadController;
use App\Http\Controllers\TransactionController;
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

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('api.logout');

    Route::post('/statements/upload', [StatementUploadController::class, 'store'])
        ->middleware('throttle:statements-upload')
        ->name('api.statements.upload');

    // §8 — read endpoints share a mild per-user throttle.
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/user', [AuthenticatedSessionController::class, 'show'])
            ->name('api.user');

        Route::get('/statements', [StatementImportController::class, 'index'])
            ->name('api.statements.index');

        Route::get('/statements/{statementImport}', [StatementImportController::class, 'show'])
            ->name('api.statements.show');

        Route::get('/transactions', [TransactionController::class, 'index'])
            ->name('api.transactions.index');

        Route::get('/analytics/dashboard', [AnalyticsController::class, 'dashboard'])
            ->name('api.analytics.dashboard');

        Route::get('/categories', [CategoryController::class, 'index'])
            ->name('api.categories.index');
    });
});

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
use App\Http\Controllers\CreditCardController;
use App\Http\Controllers\DebtorController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\InstallmentPlanController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
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

    // §8 — SPA reads/writes share api-spa (180/min, PT message).
    Route::middleware('throttle:api-spa')->group(function () {
        Route::get('/user', [AuthenticatedSessionController::class, 'show'])
            ->name('api.user');

        // Profile self-service (PLAN_PERFIL_BRANDING §2.2) — GET /api/user permanece canônico.
        Route::patch('/profile', [ProfileController::class, 'update'])
            ->middleware('throttle:30,1')
            ->name('api.profile.update');
        Route::post('/profile/avatar', [ProfileController::class, 'storeAvatar'])
            ->middleware('throttle:10,1')
            ->name('api.profile.avatar.store');
        Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])
            ->middleware('throttle:30,1')
            ->name('api.profile.avatar.destroy');

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
    Route::middleware('throttle:api-spa')->group(function () {
        Route::get('/transactions', [TransactionController::class, 'index'])
            ->name('api.transactions.index');

        Route::get('/transactions/count', [TransactionController::class, 'count'])
            ->name('api.transactions.count');

        Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])
            ->name('api.transactions.show');

        Route::match(['patch', 'put'], '/transactions/{transaction}', [TransactionController::class, 'update'])
            ->name('api.transactions.update');

        Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])
            ->name('api.transactions.destroy');
    });

    Route::post('/transactions', [TransactionController::class, 'store'])
        ->middleware(['quota:manual_transactions', 'throttle:api-spa'])
        ->name('api.transactions.store');

    Route::post('/transactions/wipe', [TransactionController::class, 'wipe'])
        ->middleware('throttle:3,60')
        ->name('api.transactions.wipe');

    // Aliases / categorization rules (PLAN_EXPANSAO §4.2)
    Route::middleware('throttle:api-spa')->group(function () {
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
    Route::middleware('throttle:api-spa')->group(function () {
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

    // Credit cards (PLAN_CARTOES_EMPRESTIMOS §3.1 / §3.4)
    Route::middleware(['feature:credit_cards', 'throttle:api-spa'])->group(function () {
        Route::get('/credit-cards', [CreditCardController::class, 'index'])
            ->name('api.credit-cards.index');
        Route::post('/credit-cards', [CreditCardController::class, 'store'])
            ->name('api.credit-cards.store');
        Route::get('/credit-cards/{credit_card}', [CreditCardController::class, 'show'])
            ->name('api.credit-cards.show');
        Route::patch('/credit-cards/{credit_card}', [CreditCardController::class, 'update'])
            ->name('api.credit-cards.update');
        Route::post('/credit-cards/{credit_card}/link-transactions', [CreditCardController::class, 'linkTransactions'])
            ->name('api.credit-cards.link-transactions');
        Route::delete('/credit-cards/{credit_card}', [CreditCardController::class, 'destroy'])
            ->name('api.credit-cards.destroy');
    });

    // Loans / cobranças (PLAN_CARTOES_EMPRESTIMOS §3.2 / §3.4)
    Route::middleware(['feature:loans', 'throttle:api-spa'])->group(function () {
        Route::get('/debtors', [DebtorController::class, 'index'])
            ->name('api.debtors.index');
        Route::post('/debtors', [DebtorController::class, 'store'])
            ->name('api.debtors.store');
        Route::get('/debtors/{debtor}', [DebtorController::class, 'show'])
            ->name('api.debtors.show');
        Route::patch('/debtors/{debtor}', [DebtorController::class, 'update'])
            ->name('api.debtors.update');
        Route::post('/debtors/{debtor}/link-transactions', [DebtorController::class, 'linkTransactions'])
            ->name('api.debtors.link-transactions');
        Route::delete('/debtors/{debtor}', [DebtorController::class, 'destroy'])
            ->name('api.debtors.destroy');

        Route::get('/loans', [LoanController::class, 'index'])
            ->name('api.loans.index');
        Route::post('/loans', [LoanController::class, 'store'])
            ->name('api.loans.store');
        Route::get('/loans/{loan}', [LoanController::class, 'show'])
            ->name('api.loans.show');
        Route::patch('/loans/{loan}', [LoanController::class, 'update'])
            ->name('api.loans.update');
        Route::delete('/loans/{loan}', [LoanController::class, 'destroy'])
            ->name('api.loans.destroy');
        Route::post('/loans/{loan}/mark-paid', [LoanController::class, 'markPaid'])
            ->name('api.loans.mark-paid');
        Route::post('/loans/{loan}/cancel', [LoanController::class, 'cancel'])
            ->name('api.loans.cancel');

        Route::get('/installment-plans', [InstallmentPlanController::class, 'index'])
            ->name('api.installment-plans.index');
        Route::post('/installment-plans', [InstallmentPlanController::class, 'store'])
            ->name('api.installment-plans.store');
        Route::get('/installment-plans/{installment_plan}', [InstallmentPlanController::class, 'show'])
            ->name('api.installment-plans.show');
        Route::patch('/installment-plans/{installment_plan}', [InstallmentPlanController::class, 'update'])
            ->name('api.installment-plans.update');
        Route::post('/installment-plans/{installment_plan}/cancel', [InstallmentPlanController::class, 'cancel'])
            ->name('api.installment-plans.cancel');
        Route::post('/installment-plans/{installment_plan}/items/{number}/mark-paid', [InstallmentPlanController::class, 'markItemPaid'])
            ->whereNumber('number')
            ->name('api.installment-plans.items.mark-paid');
        Route::post('/installment-plans/{installment_plan}/items/{number}/mark-open', [InstallmentPlanController::class, 'markItemOpen'])
            ->whereNumber('number')
            ->name('api.installment-plans.items.mark-open');
    });

    // In-app notifications (PLAN_CARTOES_EMPRESTIMOS §5)
    Route::middleware(['feature:notifications', 'throttle:api-spa'])->group(function () {
        Route::get('/notifications', [NotificationController::class, 'index'])
            ->name('api.notifications.index');
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])
            ->name('api.notifications.unread-count');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])
            ->name('api.notifications.read-all');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])
            ->name('api.notifications.read');
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

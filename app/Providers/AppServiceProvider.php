<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\Goal;
use App\Models\StatementImport;
use App\Models\Transaction;
use App\Models\TransactionAlias;
use App\Models\User;
use App\Parsers\NubankCsvParser;
use App\Parsers\OfxParser;
use App\Parsers\StatementParserResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(StatementParserResolver::class, function ($app) {
            return new StatementParserResolver([
                $app->make(\App\Parsers\NubankCreditCardCsvParser::class),
                $app->make(NubankCsvParser::class),
                $app->make(OfxParser::class),
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureGates();
        $this->configureRateLimiting();
        $this->configureRouteBindings();
    }

    /**
     * PLAN_EXPANSAO §2.1 — ability matrix from config/aura.php.
     * Admin bypass via Gate::before, still respecting is_active.
     */
    private function configureGates(): void
    {
        Gate::before(function (User $user, string $ability) {
            if (! $user->is_active) {
                return false;
            }

            if (! $this->abilityFeatureEnabled($ability)) {
                return false;
            }

            // Admin bypass only for named product abilities (config/aura.php),
            // not for Eloquent policy CRUD verbs (view/update/delete), so ownership
            // checks on Goal/Transaction/Alias remain effective.
            $named = array_keys(config('aura.abilities', []));
            if ($user->isAdmin() && in_array($ability, $named, true)) {
                return true;
            }

            return null;
        });

        foreach (array_keys(config('aura.abilities', [])) as $ability) {
            Gate::define($ability, function (User $user) use ($ability) {
                if (! $user->is_active) {
                    return false;
                }

                if (! $this->abilityFeatureEnabled($ability)) {
                    return false;
                }

                /** @var array<string, list<string>> $abilities */
                $abilities = config('aura.abilities', []);
                $roles = $abilities[$ability] ?? [];

                $role = $user->role instanceof UserRole
                    ? $user->role->value
                    : (string) $user->role;

                return in_array($role, $roles, true);
            });
        }
    }

    /**
     * PLAN_EXPANSAO §9.3 — feature flags gate named abilities.
     */
    private function abilityFeatureEnabled(string $ability): bool
    {
        /** @var array<string, string|null> $map */
        $map = config('aura.ability_features', []);

        if (! array_key_exists($ability, $map)) {
            return true;
        }

        $featureKey = $map[$ability];
        if ($featureKey === null || $featureKey === '') {
            return true;
        }

        return (bool) config('aura.features.'.$featureKey, true);
    }

    /**
     * Owner-scoped route params (404 for other users).
     * Impersonation is out of scope for this cycle.
     */
    private function configureRouteBindings(): void
    {
        Route::bind('statementImport', function (string $value): StatementImport {
            $userId = auth()->id();
            abort_if($userId === null, 401);

            return StatementImport::query()
                ->whereKey($value)
                ->where('user_id', $userId)
                ->firstOrFail();
        });

        Route::bind('transaction', function (string $value): Transaction {
            $userId = auth()->id();
            abort_if($userId === null, 401);

            return Transaction::query()
                ->whereKey($value)
                ->where('user_id', $userId)
                ->firstOrFail();
        });

        Route::bind('goal', function (string $value): Goal {
            $userId = auth()->id();
            abort_if($userId === null, 401);

            return Goal::query()
                ->whereKey($value)
                ->where('user_id', $userId)
                ->firstOrFail();
        });

        Route::bind('alias', function (string $value): TransactionAlias {
            $userId = auth()->id();
            abort_if($userId === null, 401);

            return TransactionAlias::query()
                ->whereKey($value)
                ->where('user_id', $userId)
                ->firstOrFail();
        });
    }

    /**
     * Named limiters for Etapa C (§1.7.1).
     * Applied to routes in §1.7.2 (`throttle:login`, `throttle:statements-upload`).
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));
            $perEmailIp = (int) env('RATE_LIMIT_LOGIN_PER_EMAIL', 5);
            $perIp = (int) env('RATE_LIMIT_LOGIN_PER_IP', 20);

            return [
                Limit::perMinute($perEmailIp)
                    ->by($email.'|'.$request->ip())
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'message' => 'Muitas tentativas de login. Tente novamente em breve.',
                        ], 429, $headers);
                    }),
                Limit::perMinute($perIp)
                    ->by($request->ip())
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'message' => 'Muitas tentativas de login neste IP. Tente novamente em breve.',
                        ], 429, $headers);
                    }),
            ];
        });

        RateLimiter::for('statements-upload', function (Request $request) {
            $userId = $request->user()?->id ?: $request->ip();
            $perUser = (int) env('RATE_LIMIT_UPLOAD_PER_USER', 10);

            return Limit::perMinute($perUser)
                ->by('upload|'.$userId)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Limite de uploads excedido. Tente novamente em breve.',
                    ], 429, $headers);
                });
        });
    }
}

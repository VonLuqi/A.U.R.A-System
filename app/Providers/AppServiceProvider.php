<?php

namespace App\Providers;

use App\Models\StatementImport;
use App\Parsers\NubankCsvParser;
use App\Parsers\OfxParser;
use App\Parsers\StatementParserResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        $this->configureRateLimiting();
        $this->configureRouteBindings();
    }

    /**
     * §6.2 — StatementImport route param is scoped to the authenticated owner (404 otherwise).
     * §6.3 Policy adds authorize() defense-in-depth in StatementImportController.
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

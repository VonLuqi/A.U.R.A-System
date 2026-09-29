<?php

use App\Exceptions\InvalidStatementException;
use App\Exceptions\UnsupportedStatementFormatException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // API routes are loaded from routes/web.php under prefix `api` + `web`
        // middleware (Opção A). Do NOT register `api:` here — the default API
        // stack is stateless (no session/CSRF) and would conflict with SPA auth.
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // SPA: guests hit the React shell (Etapa D handles /login client-side).
        // Never use route('login') — that named route does not exist (API-only auth).
        $middleware->redirectGuestsTo(fn () => '/');

        // §1.8: guest = idempotent login for API (200 + user), not redirect/409.
        // §2.1: active / role / quota middlewares for multi-user expansion.
        $middleware->alias([
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'role' => \App\Http\Middleware\EnsureRole::class,
            'quota' => \App\Http\Middleware\EnforceUsageQuota::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Prefer JSON for /api/* so FormRequest/auth errors match SPA contract (Etapa C).
        $exceptions->shouldRenderJsonWhen(function (Request $request, $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Force stable unauthenticated payload for API / JSON clients.
        $exceptions->renderable(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });

        $exceptions->renderable(function (\App\Exceptions\UsageLimitExceededException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'error_code' => 'usage_limit_exceeded',
                    'metric' => $e->metric,
                    'limit' => $e->limit,
                    'used' => $e->used,
                ], 429);
            }
        });

        // §3.6 / §4.4 — domain statement errors → HTTP 422 JSON (messages already sanitized).
        $exceptions->renderable(function (UnsupportedStatementFormatException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'error_code' => $e->errorCode,
                    'format' => $e->format,
                    'source' => $e->source,
                ], 422);
            }
        });

        $exceptions->renderable(function (InvalidStatementException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $payload = [
                    'message' => $e->getMessage(),
                    'error_code' => $e->errorCode,
                ];

                if ($e->importId !== null) {
                    $payload['import_id'] = $e->importId;
                }

                return response()->json($payload, 422);
            }
        });

        // §4.4 — never leak SQLSTATE / SQL / bindings / absolute paths / traces when APP_DEBUG=false.
        $exceptions->renderable(function (QueryException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            if (config('app.debug')) {
                return null; // Laravel default debug JSON
            }

            return response()->json([
                'message' => 'Não foi possível processar a solicitação.',
            ], 500);
        });

        $exceptions->renderable(function (\Throwable $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            if (config('app.debug')) {
                return null;
            }

            // Domain / auth / validation / HTTP exceptions keep their dedicated renderers.
            if ($e instanceof AuthenticationException
                || $e instanceof InvalidStatementException
                || $e instanceof UnsupportedStatementFormatException
                || $e instanceof \App\Exceptions\UsageLimitExceededException
                || $e instanceof QueryException
                || $e instanceof ValidationException
                || $e instanceof HttpExceptionInterface
            ) {
                return null;
            }

            return response()->json([
                'message' => 'Não foi possível processar a solicitação.',
            ], 500);
        });
    })->create();

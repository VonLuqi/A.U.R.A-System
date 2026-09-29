<?php

namespace App\Http\Middleware;

use App\Http\Resources\AuthUserResource;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guest middleware for Aura (Etapa C §1.8 / PLAN_EXPANSAO §8.1).
 *
 * Decision: if already authenticated, POST /api/login returns 200 + current user
 * (idempotent) instead of redirect/409. SPA never needs a second login error.
 */
class RedirectIfAuthenticated
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (! Auth::guard($guard)->check()) {
                continue;
            }

            if ($request->is('api/*') || $request->expectsJson()) {
                /** @var User $user */
                $user = Auth::guard($guard)->user();

                return response()->json([
                    'user' => (new AuthUserResource($user))->resolve(),
                ]);
            }

            return redirect('/');
        }

        return $next($request);
    }
}

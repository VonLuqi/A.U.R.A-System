<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Block inactive users (PLAN_EXPANSAO §2.1).
 */
class EnsureUserIsActive
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            return response()->json([
                'message' => 'Conta desativada.',
            ], 403);
        }

        return $next($request);
    }
}

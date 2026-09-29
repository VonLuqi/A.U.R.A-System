<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use ValueError;

/**
 * Require one of the given roles (PLAN_EXPANSAO §2.1).
 *
 * Usage: middleware('role:admin') or middleware('role:admin,subadmin')
 */
class EnsureRole
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $allowed = [];
        foreach ($roles as $role) {
            foreach (explode(',', $role) as $piece) {
                $piece = strtolower(trim($piece));
                if ($piece !== '') {
                    $allowed[] = $piece;
                }
            }
        }

        try {
            $current = $user->role instanceof UserRole
                ? $user->role->value
                : (string) $user->role;
        } catch (ValueError) {
            $current = '';
        }

        if (! in_array($current, $allowed, true)) {
            return response()->json([
                'message' => 'Acesso negado para este papel.',
            ], 403);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Early-return when an Aura feature flag is off (PLAN_CARTOES §3.4 / PLAN_EXPANSAO §9.3).
 *
 * Usage: middleware('feature:credit_cards') — key under config('aura.features.*').
 * Complements Gate ability_features (policies still deny when flag is false).
 */
class EnsureFeatureEnabled
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! (bool) config('aura.features.'.$feature, false)) {
            return response()->json([
                'message' => 'Este recurso está temporariamente desativado.',
                'error_code' => 'feature_disabled',
                'feature' => $feature,
            ], 403);
        }

        return $next($request);
    }
}

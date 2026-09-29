<?php

namespace App\Http\Middleware;

use App\Exceptions\UsageLimitExceededException;
use App\Services\UsageLimitService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assert usage quota before the action (PLAN_EXPANSAO §2.1).
 *
 * Usage: middleware('quota:uploads') or middleware('quota:manual_transactions')
 * Increment on success is left to the controller/service after a successful write
 * (so failed uploads do not consume quota).
 */
class EnforceUsageQuota
{
    public function __construct(
        private readonly UsageLimitService $limits,
    ) {}

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $metric = UsageLimitService::METRIC_UPLOADS): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $this->limits->assertCan($user, $metric);
        } catch (UsageLimitExceededException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => 'usage_limit_exceeded',
                'metric' => $e->metric,
                'limit' => $e->limit,
                'used' => $e->used,
            ], 429);
        }

        return $next($request);
    }
}

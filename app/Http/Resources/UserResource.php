<?php

namespace App\Http\Resources;

use App\Services\UsageLimitService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin user management payload (PLAN_EXPANSAO §2.3).
 *
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var UsageLimitService $limits */
        $limits = app(UsageLimitService::class);
        $limits->resetPeriodIfNeeded($this->resource);
        $this->resource->refresh();

        $snapshot = $limits->limitsSnapshot($this->resource);

        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'email' => (string) $this->email,
            'role' => $this->role instanceof \BackedEnum
                ? $this->role->value
                : (string) $this->role,
            'is_active' => (bool) $this->is_active,
            'limits' => $snapshot,
            'usage' => [
                'uploads_used' => (int) $this->uploads_used,
                'manual_transactions_used' => (int) $this->manual_transactions_used,
                'quota_period_starts_at' => $this->quota_period_starts_at?->toIso8601String(),
                'uploads_remaining' => $limits->remaining($this->resource, UsageLimitService::METRIC_UPLOADS),
                'manual_transactions_remaining' => $limits->remaining(
                    $this->resource,
                    UsageLimitService::METRIC_MANUAL_TRANSACTIONS
                ),
            ],
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

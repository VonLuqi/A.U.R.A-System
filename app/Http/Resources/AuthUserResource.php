<?php

namespace App\Http\Resources;

use App\Services\UsageLimitService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Session / SPA auth payload (PLAN_EXPANSAO §8.1 · Etapa I).
 *
 * Used by GET /api/user and POST /api/login — includes role, limits, usage, abilities.
 *
 * Contrato Etapa I: `avatar_url` (`string|null`) — URL pública do avatar no disk `public`
 * (`/storage/avatars/…`) ou `null` se `users.avatar_path` estiver vazio.
 * Self-service edita nome/senha/avatar via ProfileController; `email` é somente leitura.
 *
 * @mixin \App\Models\User
 */
class AuthUserResource extends JsonResource
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
        $role = $this->role instanceof \BackedEnum
            ? $this->role->value
            : (string) $this->role;

        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'email' => (string) $this->email,
            'avatar_url' => $this->avatarUrl(),
            'role' => $role,
            'is_active' => (bool) $this->is_active,
            'limits' => $snapshot,
            'usage' => [
                'uploads_used' => (int) $this->uploads_used,
                'manual_transactions_used' => (int) $this->manual_transactions_used,
                'quota_period_starts_at' => $this->quota_period_starts_at?->toIso8601String(),
                'uploads_remaining' => $limits->remaining(
                    $this->resource,
                    UsageLimitService::METRIC_UPLOADS
                ),
                'manual_transactions_remaining' => $limits->remaining(
                    $this->resource,
                    UsageLimitService::METRIC_MANUAL_TRANSACTIONS
                ),
                'goals_used' => $limits->goalsUsed($this->resource),
                'goals_remaining' => $limits->goalsRemaining($this->resource),
                'aliases_used' => $limits->aliasesUsed($this->resource),
                'aliases_remaining' => $limits->aliasesRemaining($this->resource),
                'credit_cards_used' => $limits->creditCardsUsed($this->resource),
                'credit_cards_remaining' => $limits->creditCardsRemaining($this->resource),
                'loans_used' => $limits->loansUsed($this->resource),
                'loans_remaining' => $limits->loansRemaining($this->resource),
            ],
            'abilities' => $this->abilitiesForUser(),
            'features' => [
                'manual_transactions' => (bool) config('aura.features.manual_transactions', true),
                'goals' => (bool) config('aura.features.goals', true),
                'aliases' => (bool) config('aura.features.aliases', true),
                'credit_card_upload' => (bool) config('aura.features.credit_card_upload', true),
                'admin_users' => (bool) config('aura.features.admin_users', true),
                'credit_cards' => (bool) config('aura.features.credit_cards', false),
                'loans' => (bool) config('aura.features.loans', false),
                'notifications' => (bool) config('aura.features.notifications', false),
            ],
        ];
    }

    /**
     * Named Gate abilities from config('aura.abilities') the user passes.
     *
     * @return list<string>
     */
    private function abilitiesForUser(): array
    {
        $allowed = [];

        foreach (array_keys(config('aura.abilities', [])) as $ability) {
            if ($this->resource->can($ability)) {
                $allowed[] = $ability;
            }
        }

        sort($allowed);

        return $allowed;
    }
}

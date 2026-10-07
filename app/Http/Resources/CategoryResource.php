<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Category payload for nested transaction rows and GET /api/categories (§5.6).
 *
 * @mixin \App\Models\Category
 */
class CategoryResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     type: string,
     *     color: string|null,
     *     is_system: bool,
     *     transactions_count?: int,
     *     goals_count?: int,
     *     aliases_count?: int,
     *     usage_count?: int
     * }
     */
    public function toArray(Request $request): array
    {
        $payload = [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'slug' => (string) $this->slug,
            'type' => (string) $this->type,
            'color' => $this->color !== null ? (string) $this->color : null,
            'is_system' => (bool) $this->is_system,
        ];

        if ($this->relationLoaded('transactions') || isset($this->transactions_count)) {
            $payload['transactions_count'] = (int) ($this->transactions_count ?? 0);
        }

        if ($this->relationLoaded('goals') || isset($this->goals_count)) {
            $payload['goals_count'] = (int) ($this->goals_count ?? 0);
        }

        if ($this->relationLoaded('transactionAliases') || isset($this->transaction_aliases_count)) {
            $payload['aliases_count'] = (int) ($this->transaction_aliases_count ?? 0);
        }

        if (
            array_key_exists('transactions_count', $payload)
            || array_key_exists('goals_count', $payload)
            || array_key_exists('aliases_count', $payload)
        ) {
            $payload['usage_count'] = (int) ($payload['transactions_count'] ?? 0)
                + (int) ($payload['goals_count'] ?? 0)
                + (int) ($payload['aliases_count'] ?? 0);
        }

        return $payload;
    }
}

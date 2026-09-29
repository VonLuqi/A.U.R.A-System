<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Alias / categorization rule payload (PLAN_EXPANSAO §4.2).
 *
 * @mixin \App\Models\TransactionAlias
 */
class TransactionAliasResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'match_type' => $this->match_type instanceof \BackedEnum
                ? $this->match_type->value
                : (string) $this->match_type,
            'match_pattern' => (string) $this->match_pattern,
            'display_name' => (string) $this->display_name,
            'category_id' => $this->category_id !== null ? (int) $this->category_id : null,
            'category' => $this->when(
                $this->relationLoaded('category') && $this->category !== null,
                fn () => (new CategoryResource($this->category))->resolve(),
                null,
            ),
            'priority' => (int) $this->priority,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

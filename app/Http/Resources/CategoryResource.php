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
     * @return array{id: int, name: string, slug: string, type: string, color: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'slug' => (string) $this->slug,
            'type' => (string) $this->type,
            'color' => $this->color !== null ? (string) $this->color : null,
        ];
    }
}

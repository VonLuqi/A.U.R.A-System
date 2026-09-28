<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Stable transaction row for GET /api/transactions (Etapa C §5.4.3).
 *
 * @mixin \App\Models\Transaction
 */
class TransactionResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     occurred_on: string,
     *     description: string,
     *     amount: string,
     *     type: string,
     *     category: array{id: int, name: string, slug: string, type: string, color: string|null}|null,
     *     statement_import_id: int,
     *     external_id: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'occurred_on' => $this->occurred_on?->format('Y-m-d') ?? (string) $this->occurred_on,
            'description' => (string) $this->description,
            'amount' => number_format((float) $this->amount, 2, '.', ''),
            'type' => (string) $this->type,
            'category' => $this->when(
                $this->relationLoaded('category') && $this->category !== null,
                fn () => (new CategoryResource($this->category))->resolve(),
                null,
            ),
            'statement_import_id' => (int) $this->statement_import_id,
            'external_id' => $this->external_id !== null ? (string) $this->external_id : null,
        ];
    }
}

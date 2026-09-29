<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Stable transaction row for transactions API (Etapa C §5.4.3 / PLAN_EXPANSAO §3.1).
 *
 * @mixin \App\Models\Transaction
 */
class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

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
            'user_id' => (int) $this->user_id,
            'source_kind' => $this->source_kind instanceof \BackedEnum
                ? $this->source_kind->value
                : (string) $this->source_kind,
            'statement_import_id' => $this->statement_import_id !== null
                ? (int) $this->statement_import_id
                : null,
            'external_id' => $this->external_id !== null ? (string) $this->external_id : null,
            'notes' => is_array($this->raw_payload)
                ? ($this->raw_payload['notes'] ?? null)
                : null,
            'editable' => $user !== null && $user->can('update', $this->resource),
            'deletable' => $user !== null && $user->can('delete', $this->resource),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Credit card payload (PLAN_CARTOES_EMPRESTIMOS §3.1).
 *
 * @mixin \App\Models\CreditCard
 */
class CreditCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'limit_amount' => $this->limit_amount !== null
                ? number_format((float) $this->limit_amount, 2, '.', '')
                : null,
            'currency' => (string) $this->currency,
            'closing_day' => (int) $this->closing_day,
            'due_day' => (int) $this->due_day,
            'next_due_on' => $this->nextDueDate()->toDateString(),
            'next_closing_on' => $this->nextClosingDate()->toDateString(),
            'last_four' => $this->last_four !== null ? (string) $this->last_four : null,
            'is_active' => (bool) $this->is_active,
            'is_default' => (bool) $this->is_default,
            'notes' => $this->notes !== null ? (string) $this->notes : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

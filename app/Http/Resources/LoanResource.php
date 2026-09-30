<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Loan / cobrança payload (PLAN_CARTOES_EMPRESTIMOS §3.2).
 *
 * @mixin \App\Models\Loan
 */
class LoanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $kind = $this->kind instanceof \BackedEnum ? $this->kind->value : (string) $this->kind;
        $status = $this->status instanceof \BackedEnum ? $this->status->value : (string) $this->status;

        return [
            'id' => (int) $this->id,
            'debtor_id' => $this->debtor_id !== null ? (int) $this->debtor_id : null,
            'debtor' => $this->when(
                $this->relationLoaded('debtor') && $this->debtor !== null,
                fn () => [
                    'id' => (int) $this->debtor->id,
                    'name' => (string) $this->debtor->name,
                ],
                null,
            ),
            'debtor_name' => (string) $this->debtor_name,
            'kind' => $kind,
            'status' => $status,
            'amount' => number_format((float) $this->amount, 2, '.', ''),
            'paid_amount' => number_format((float) $this->paid_amount, 2, '.', ''),
            'remaining_amount' => number_format($this->remainingAmount(), 2, '.', ''),
            'currency' => (string) $this->currency,
            'lent_on' => $this->lent_on?->format('Y-m-d'),
            'due_on' => $this->due_on?->format('Y-m-d'),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'is_overdue' => $this->isOverdue(),
            'credit_card_id' => $this->credit_card_id !== null ? (int) $this->credit_card_id : null,
            'credit_card' => $this->when(
                $this->relationLoaded('creditCard') && $this->creditCard !== null,
                fn () => [
                    'id' => (int) $this->creditCard->id,
                    'name' => (string) $this->creditCard->name,
                ],
                null,
            ),
            'notes' => $this->notes !== null ? (string) $this->notes : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

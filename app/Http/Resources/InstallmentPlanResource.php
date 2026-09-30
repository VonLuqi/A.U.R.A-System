<?php

namespace App\Http\Resources;

use App\Models\InstallmentItem;
use App\Models\InstallmentPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InstallmentPlan
 */
class InstallmentPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof \BackedEnum ? $this->status->value : (string) $this->status;
        $paidCount = $this->paidCount();
        $openRemaining = $this->openRemainingTotal();

        return [
            'id' => (int) $this->id,
            'title' => (string) $this->title,
            'total_count' => (int) $this->total_count,
            'paid_count' => $paidCount,
            'installment_amount' => number_format((float) $this->installment_amount, 2, '.', ''),
            'open_remaining_total' => number_format($openRemaining, 2, '.', ''),
            'currency' => (string) $this->currency,
            'status' => $status,
            'notes' => $this->notes !== null ? (string) $this->notes : null,
            'credit_card_id' => $this->credit_card_id !== null ? (int) $this->credit_card_id : null,
            'credit_card' => $this->when(
                $this->relationLoaded('creditCard') && $this->creditCard !== null,
                fn () => [
                    'id' => (int) $this->creditCard->id,
                    'name' => (string) $this->creditCard->name,
                ],
                null,
            ),
            'debtor_id' => $this->debtor_id !== null ? (int) $this->debtor_id : null,
            'debtor' => $this->when(
                $this->relationLoaded('debtor') && $this->debtor !== null,
                fn () => [
                    'id' => (int) $this->debtor->id,
                    'name' => (string) $this->debtor->name,
                ],
                null,
            ),
            'items' => $this->when(
                $this->relationLoaded('items'),
                fn () => $this->items->map(fn (InstallmentItem $item) => [
                    'id' => (int) $item->id,
                    'number' => (int) $item->number,
                    'amount' => number_format((float) $item->amount, 2, '.', ''),
                    'due_on' => $item->due_on?->format('Y-m-d'),
                    'status' => $item->status instanceof \BackedEnum
                        ? $item->status->value
                        : (string) $item->status,
                    'paid_at' => $item->paid_at?->toIso8601String(),
                    'transaction_id' => $item->transaction_id !== null
                        ? (int) $item->transaction_id
                        : null,
                    'loan_id' => $item->loan_id !== null ? (int) $item->loan_id : null,
                ])->values()->all(),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

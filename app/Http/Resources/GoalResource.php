<?php

namespace App\Http\Resources;

use App\Services\GoalService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Goal payload (PLAN_EXPANSAO §7.2).
 *
 * @mixin \App\Models\Goal
 */
class GoalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $kind = $this->kind instanceof \BackedEnum ? $this->kind->value : (string) $this->kind;
        $status = $this->status instanceof \BackedEnum ? $this->status->value : (string) $this->status;
        $progressMode = $this->isLinked()
            ? GoalService::PROGRESS_LINKED
            : GoalService::PROGRESS_MANUAL;

        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'kind' => $kind,
            'status' => $status,
            'progress_mode' => $progressMode,
            'target_amount' => number_format((float) $this->target_amount, 2, '.', ''),
            'current_amount' => number_format((float) $this->current_amount, 2, '.', ''),
            'remaining_amount' => $this->remainingAmount(),
            'progress_percent' => $this->progressPercent(),
            'currency' => (string) $this->currency,
            'deadline_on' => $this->deadline_on?->format('Y-m-d'),
            'category_id' => $this->category_id !== null ? (int) $this->category_id : null,
            'category' => $this->when(
                $this->relationLoaded('category') && $this->category !== null,
                fn () => (new CategoryResource($this->category))->resolve(),
                null,
            ),
            'linked_description_pattern' => $this->linked_description_pattern !== null
                ? (string) $this->linked_description_pattern
                : null,
            'metadata' => is_array($this->metadata) ? $this->metadata : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Compact row for analytics dashboard (no N+1 — category optional).
     *
     * @return array<string, mixed>
     */
    public function toSummaryArray(): array
    {
        $kind = $this->kind instanceof \BackedEnum ? $this->kind->value : (string) $this->kind;
        $status = $this->status instanceof \BackedEnum ? $this->status->value : (string) $this->status;

        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'kind' => $kind,
            'status' => $status,
            'progress_mode' => $this->isLinked()
                ? GoalService::PROGRESS_LINKED
                : GoalService::PROGRESS_MANUAL,
            'progress_percent' => $this->progressPercent(),
            'remaining_amount' => $this->remainingAmount(),
            'target_amount' => number_format((float) $this->target_amount, 2, '.', ''),
            'current_amount' => number_format((float) $this->current_amount, 2, '.', ''),
            'deadline_on' => $this->deadline_on?->format('Y-m-d'),
        ];
    }
}

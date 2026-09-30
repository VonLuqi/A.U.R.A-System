<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Debtor
 */
class DebtorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'notes' => $this->notes !== null ? (string) $this->notes : null,
            'open_loans_count' => (int) ($this->open_loans_count ?? 0),
            'open_remaining_total' => number_format(
                (float) ($this->open_remaining_total ?? 0),
                2,
                '.',
                '',
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

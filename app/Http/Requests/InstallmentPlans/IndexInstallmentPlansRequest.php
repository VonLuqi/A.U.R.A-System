<?php

namespace App\Http\Requests\InstallmentPlans;

use App\Models\InstallmentPlan;
use Illuminate\Foundation\Http\FormRequest;

class IndexInstallmentPlansRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', InstallmentPlan::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:160'],
            'status' => ['sometimes', 'nullable', 'string', 'max:32'],
            'debtor_id' => ['sometimes', 'nullable', 'integer'],
            'credit_card_id' => ['sometimes', 'nullable', 'integer'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'backfill' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{q?: string|null, status?: string|null, debtor_id?: int|null, credit_card_id?: int|null}
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'q' => $validated['q'] ?? null,
            'status' => $validated['status'] ?? null,
            'debtor_id' => isset($validated['debtor_id']) ? (int) $validated['debtor_id'] : null,
            'credit_card_id' => isset($validated['credit_card_id'])
                ? (int) $validated['credit_card_id']
                : null,
        ];
    }

    public function perPage(): int
    {
        return (int) ($this->validated()['per_page'] ?? 50);
    }

    public function shouldBackfill(): bool
    {
        return $this->boolean('backfill', true);
    }
}

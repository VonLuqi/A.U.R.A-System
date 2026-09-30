<?php

namespace App\Http\Requests\InstallmentPlans;

use App\Models\InstallmentPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInstallmentPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var InstallmentPlan $plan */
        $plan = $this->route('installment_plan');

        return $this->user()?->can('update', $plan) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = (int) $this->user()->id;

        return [
            'title' => ['sometimes', 'string', 'min:1', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'debtor_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('debtors', 'id')->where(fn ($q) => $q->where('user_id', $userId)),
            ],
            'credit_card_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('credit_cards', 'id')->where(fn ($q) => $q->where('user_id', $userId)),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->validated();
    }
}

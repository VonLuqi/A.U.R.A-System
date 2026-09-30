<?php

namespace App\Http\Requests\InstallmentPlans;

use App\Models\InstallmentPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInstallmentPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', InstallmentPlan::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = (int) $this->user()->id;

        return [
            'title' => ['required', 'string', 'min:1', 'max:255'],
            'total_count' => ['required', 'integer', 'min:2', 'max:120'],
            'installment_amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'first_due_on' => ['required', 'date', 'date_format:Y-m-d'],
            'credit_card_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('credit_cards', 'id')->where(fn ($q) => $q->where('user_id', $userId)),
            ],
            'debtor_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('debtors', 'id')->where(fn ($q) => $q->where('user_id', $userId)),
            ],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'paid_numbers' => ['sometimes', 'array'],
            'paid_numbers.*' => ['integer', 'min:1'],
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

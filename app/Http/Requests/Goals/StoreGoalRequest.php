<?php

namespace App\Http\Requests\Goals;

use App\Enums\GoalKind;
use App\Enums\GoalStatus;
use App\Models\Goal;
use App\Services\GoalService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Goal::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:160'],
            'kind' => ['required', 'string', Rule::in(GoalKind::values())],
            'target_amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'current_amount' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'deadline_on' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'linked_description_pattern' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', Rule::in(GoalStatus::values())],
            'progress_mode' => ['sometimes', 'string', Rule::in([
                GoalService::PROGRESS_MANUAL,
                GoalService::PROGRESS_LINKED,
            ])],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $mode = strtolower(trim((string) $this->input('progress_mode', '')));
            if ($mode !== GoalService::PROGRESS_LINKED) {
                // Infer linked from fields when progress_mode omitted.
                $hasLink = $this->filled('category_id') || $this->filled('linked_description_pattern');
                if ($mode === GoalService::PROGRESS_MANUAL || ! $hasLink) {
                    return;
                }
                $mode = GoalService::PROGRESS_LINKED;
            }

            if ($mode === GoalService::PROGRESS_LINKED
                && ! $this->filled('category_id')
                && ! $this->filled('linked_description_pattern')
            ) {
                $validator->errors()->add(
                    'progress_mode',
                    'Modo linked exige category_id e/ou linked_description_pattern.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome da meta.',
            'kind.in' => 'Tipo de meta inválido. Use savings ou debt_payoff.',
            'target_amount.gt' => 'O valor-alvo deve ser maior que zero.',
            'category_id.exists' => 'Categoria inválida.',
            'progress_mode.in' => 'progress_mode inválido. Use manual ou linked.',
            'status.in' => 'Status inválido.',
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

<?php

namespace App\Http\Requests\Loans;

use App\Enums\LoanKind;
use App\Models\Loan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Loan $loan */
        $loan = $this->route('loan');

        return $this->user()?->can('update', $loan) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = (int) $this->user()->id;
        $kind = $this->input('kind');

        return [
            'debtor_name' => ['sometimes', 'string', 'min:1', 'max:160'],
            'kind' => ['sometimes', 'string', Rule::in(LoanKind::values())],
            'credit_card_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::requiredIf(fn () => $kind === LoanKind::CardLimit->value),
                Rule::prohibitedIf(fn () => $kind === LoanKind::Cash->value),
                Rule::exists('credit_cards', 'id')->where(fn ($q) => $q->where('user_id', $userId)),
            ],
            'amount' => ['sometimes', 'numeric', 'gt:0', 'max:999999999999.99'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'lent_on' => ['sometimes', 'date', 'date_format:Y-m-d'],
            'due_on' => ['sometimes', 'date', 'date_format:Y-m-d'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Loan $loan */
            $loan = $this->route('loan');
            $lentOn = $this->input('lent_on', $loan->lent_on?->format('Y-m-d'));
            $dueOn = $this->input('due_on', $loan->due_on?->format('Y-m-d'));

            if (is_string($lentOn) && is_string($dueOn) && $dueOn < $lentOn) {
                $validator->errors()->add(
                    'due_on',
                    'A data de cobrança deve ser igual ou posterior à data do empréstimo.'
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
            'kind.in' => 'Tipo inválido. Use cash ou card_limit.',
            'credit_card_id.exists' => 'Cartão inválido ou não pertence a você.',
            'amount.gt' => 'O valor deve ser maior que zero.',
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

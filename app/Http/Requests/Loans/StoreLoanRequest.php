<?php

namespace App\Http\Requests\Loans;

use App\Enums\LoanKind;
use App\Models\Loan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Loan::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = (int) $this->user()->id;

        return [
            'debtor_name' => [
                Rule::requiredIf(fn () => ! $this->filled('debtor_id')),
                'nullable',
                'string',
                'min:1',
                'max:160',
            ],
            'debtor_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('debtors', 'id')->where(fn ($q) => $q->where('user_id', $userId)),
            ],
            'kind' => ['required', 'string', Rule::in(LoanKind::values())],
            'credit_card_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn () => $this->input('kind') === LoanKind::CardLimit->value),
                Rule::prohibitedIf(fn () => $this->input('kind') === LoanKind::Cash->value),
                Rule::exists('credit_cards', 'id')->where(fn ($q) => $q->where('user_id', $userId)),
            ],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'lent_on' => ['required', 'date', 'date_format:Y-m-d'],
            'due_on' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:lent_on'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'create_expense' => ['sometimes', 'boolean'],
            'expense_description' => [
                Rule::requiredIf(fn () => $this->boolean('create_expense')),
                'nullable',
                'string',
                'min:1',
                'max:500',
            ],
            'expense_amount' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:999999999999.99'],
            'expense_occurred_on' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d'],
            'expense_category_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:categories,id',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('kind') === LoanKind::CardLimit->value && ! $this->filled('credit_card_id')) {
                $validator->errors()->add('credit_card_id', 'Informe o cartão quando o tipo for limite do cartão.');
            }

            if ($this->boolean('create_expense') && ! config('aura.features.manual_transactions', true)) {
                $validator->errors()->add(
                    'create_expense',
                    'Criação de saídas manuais está temporariamente desabilitada.',
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
            'debtor_name.required' => 'Informe ou selecione a pessoa.',
            'debtor_id.exists' => 'Pessoa inválida ou não pertence a você.',
            'kind.in' => 'Tipo inválido. Use cash ou card_limit.',
            'credit_card_id.required' => 'Informe o cartão quando o tipo for limite do cartão.',
            'credit_card_id.prohibited' => 'Empréstimo em dinheiro não deve ter cartão vinculado.',
            'credit_card_id.exists' => 'Cartão inválido ou não pertence a você.',
            'amount.gt' => 'O valor deve ser maior que zero.',
            'due_on.after_or_equal' => 'A data de cobrança deve ser igual ou posterior à data do empréstimo.',
            'expense_description.required' => 'Informe o que foi comprado / a descrição da saída.',
            'expense_amount.gt' => 'O valor da saída deve ser maior que zero.',
            'expense_category_id.exists' => 'Categoria inválida.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $validated = $this->validated();

        if (array_key_exists('create_expense', $validated)) {
            $validated['create_expense'] = $this->boolean('create_expense');
        }

        return $validated;
    }
}

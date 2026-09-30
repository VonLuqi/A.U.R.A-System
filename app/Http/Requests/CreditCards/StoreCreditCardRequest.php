<?php

namespace App\Http\Requests\CreditCards;

use App\Models\CreditCard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCreditCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CreditCard::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = (int) $this->user()->id;

        return [
            'name' => [
                'required',
                'string',
                'min:1',
                'max:120',
                Rule::unique('credit_cards', 'name')->where(fn ($q) => $q->where('user_id', $userId)),
            ],
            'limit_amount' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'closing_day' => ['required', 'integer', 'between:1,31'],
            'due_day' => ['required', 'integer', 'between:1,31'],
            'last_four' => ['sometimes', 'nullable', 'digits:4'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do cartão.',
            'name.unique' => 'Você já tem um cartão com este nome.',
            'closing_day.between' => 'Dia de fechamento deve ser entre 1 e 31.',
            'due_day.between' => 'Dia de vencimento deve ser entre 1 e 31.',
            'last_four.digits' => 'Informe exatamente 4 dígitos.',
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

<?php

namespace App\Http\Requests\Debtors;

use App\Models\Debtor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDebtorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Debtor::class) ?? false;
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
                'max:160',
                Rule::unique('debtors', 'name')->where(fn ($q) => $q->where('user_id', $userId)),
            ],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome da pessoa.',
            'name.unique' => 'Você já tem uma pessoa com este nome.',
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

<?php

namespace App\Http\Requests\Debtors;

use App\Models\Debtor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDebtorRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Debtor $debtor */
        $debtor = $this->route('debtor');

        return $this->user()?->can('update', $debtor) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = (int) $this->user()->id;
        /** @var Debtor $debtor */
        $debtor = $this->route('debtor');

        return [
            'name' => [
                'sometimes',
                'string',
                'min:1',
                'max:160',
                Rule::unique('debtors', 'name')
                    ->where(fn ($q) => $q->where('user_id', $userId))
                    ->ignore($debtor->id),
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

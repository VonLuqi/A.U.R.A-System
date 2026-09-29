<?php

namespace App\Http\Requests\Transactions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/transactions — manual create (PLAN_EXPANSAO §3.1 / §3.2).
 */
class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Transaction::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'occurred_on' => ['required', 'date', 'date_format:Y-m-d'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'type' => ['required', 'string', Rule::in(['credit', 'debit'])],
            'description' => ['required', 'string', 'min:1', 'max:500'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.gt' => 'O valor deve ser maior que zero.',
            'type.in' => 'O tipo deve ser credit ou debit.',
            'category_id.exists' => 'Categoria inválida.',
        ];
    }

    /**
     * @return array{
     *     occurred_on: string,
     *     amount: string,
     *     type: string,
     *     description: string,
     *     category_id: int|null,
     *     notes: string|null
     * }
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'occurred_on' => (string) $validated['occurred_on'],
            'amount' => number_format((float) $validated['amount'], 2, '.', ''),
            'type' => (string) $validated['type'],
            'description' => (string) $validated['description'],
            'category_id' => isset($validated['category_id']) ? (int) $validated['category_id'] : null,
            'notes' => isset($validated['notes']) && is_string($validated['notes']) && $validated['notes'] !== ''
                ? $validated['notes']
                : null,
        ];
    }
}

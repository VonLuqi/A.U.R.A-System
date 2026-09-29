<?php

namespace App\Http\Requests\Transactions;

use App\Enums\TransactionSourceKind;
use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * PATCH/PUT /api/transactions/{transaction} (PLAN_EXPANSAO §3.1 / §3.2).
 *
 * Manual: wide fields. Imported: category_id (+ notes); Admin may also edit core fields.
 */
class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Transaction $transaction */
        $transaction = $this->route('transaction');

        return $this->user()?->can('update', $transaction) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'occurred_on' => ['sometimes', 'date', 'date_format:Y-m-d'],
            'amount' => ['sometimes', 'numeric', 'gt:0', 'decimal:0,2'],
            'type' => ['sometimes', 'string', Rule::in(['credit', 'debit'])],
            'description' => ['sometimes', 'string', 'min:1', 'max:500'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Transaction $transaction */
            $transaction = $this->route('transaction');
            $user = $this->user();

            if ($transaction->source_kind !== TransactionSourceKind::Import || $user === null) {
                return;
            }

            $core = ['occurred_on', 'amount', 'type', 'description'];
            foreach ($core as $field) {
                if ($this->exists($field) && ! $user->isAdmin()) {
                    $validator->errors()->add(
                        $field,
                        'Lançamentos importados não permitem alterar este campo.'
                    );
                }
            }
        });
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
     * Only keys present in the request (partial update).
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $validated = $this->validated();
        $out = [];

        foreach (['occurred_on', 'type', 'description', 'notes'] as $key) {
            if (array_key_exists($key, $validated)) {
                $out[$key] = $validated[$key];
            }
        }

        if (array_key_exists('amount', $validated)) {
            $out['amount'] = number_format((float) $validated['amount'], 2, '.', '');
        }

        if (array_key_exists('category_id', $validated)) {
            $out['category_id'] = $validated['category_id'] !== null
                ? (int) $validated['category_id']
                : null;
        }

        return $out;
    }
}

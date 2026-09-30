<?php

namespace App\Http\Requests\CreditCards;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/credit-cards/{credit_card}/link-transactions
 */
class LinkCreditCardTransactionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'transaction_ids' => ['required', 'array', 'min:1', 'max:100'],
            'transaction_ids.*' => ['integer', 'distinct', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'transaction_ids.required' => 'Selecione ao menos uma saída.',
            'transaction_ids.max' => 'Selecione no máximo 100 saídas por vez.',
        ];
    }

    /**
     * @return list<int>
     */
    public function transactionIds(): array
    {
        /** @var list<int|string> $ids */
        $ids = $this->validated('transaction_ids');

        return array_values(array_map('intval', $ids));
    }
}

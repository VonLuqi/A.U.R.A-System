<?php

namespace App\Http\Requests\Loans;

use App\Models\Loan;
use Illuminate\Foundation\Http\FormRequest;

class MarkLoanPaidRequest extends FormRequest
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
        return [
            'paid_amount' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:999999999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'paid_amount.gt' => 'O valor pago deve ser maior que zero.',
        ];
    }

    public function paidAmount(): ?float
    {
        $validated = $this->validated();

        if (! array_key_exists('paid_amount', $validated) || $validated['paid_amount'] === null) {
            return null;
        }

        return (float) $validated['paid_amount'];
    }
}

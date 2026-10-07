<?php

namespace App\Http\Requests\Transactions;

use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/transactions/wipe — bulk hard-delete for the authenticated user.
 */
class WipeTransactionsRequest extends FormRequest
{
    public const CONFIRMATION_PHRASE = 'APAGAR TUDO';

    public function authorize(): bool
    {
        return $this->user()?->can('wipe', Transaction::class) ?? false;
    }

    /**
     * @return array<string, list<string|\Illuminate\Validation\Rules\In>>
     */
    public function rules(): array
    {
        return [
            'confirmation' => [
                'required',
                'string',
                Rule::in([self::CONFIRMATION_PHRASE]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmation.required' => 'Digite a frase de confirmação.',
            'confirmation.in' => 'A frase de confirmação deve ser exatamente '.self::CONFIRMATION_PHRASE.'.',
        ];
    }

    public function confirmation(): string
    {
        return (string) $this->validated('confirmation');
    }
}

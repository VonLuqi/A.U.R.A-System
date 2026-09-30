<?php

namespace App\Http\Requests\Debtors;

use App\Models\Debtor;
use Illuminate\Foundation\Http\FormRequest;

class IndexDebtorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Debtor::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:160'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array{q: ?string}
     */
    public function filters(): array
    {
        return [
            'q' => $this->validated('q'),
        ];
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 50);
    }
}

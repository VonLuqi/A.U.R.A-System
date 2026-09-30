<?php

namespace App\Http\Requests\CreditCards;

use App\Models\CreditCard;
use Illuminate\Foundation\Http\FormRequest;

class IndexCreditCardsRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 50;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', CreditCard::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('q') && $this->input('q') === '') {
            $this->merge(['q' => null]);
        }

        if ($this->exists('is_active') && $this->input('is_active') === '') {
            $this->merge(['is_active' => null]);
        }
    }

    /**
     * @return array{q: ?string, is_active: ?bool}
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'q' => $validated['q'] ?? null,
            'is_active' => array_key_exists('is_active', $validated)
                ? ($validated['is_active'] !== null ? (bool) $validated['is_active'] : null)
                : null,
        ];
    }

    public function perPage(): int
    {
        $value = $this->validated('per_page');

        return $value !== null ? (int) $value : self::DEFAULT_PER_PAGE;
    }
}

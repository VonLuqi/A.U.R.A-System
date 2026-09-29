<?php

namespace App\Http\Requests\Categories;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/categories — create global category (user-created, not system).
 */
class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:80'],
            'type' => ['sometimes', 'string', Rule::in(['income', 'expense', 'transfer'])],
            'color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome da categoria.',
            'type.in' => 'Tipo inválido. Use income, expense ou transfer.',
            'color.regex' => 'Cor inválida. Use formato #RRGGBB.',
        ];
    }
}

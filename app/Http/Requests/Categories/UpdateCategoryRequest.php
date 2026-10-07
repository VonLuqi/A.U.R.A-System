<?php

namespace App\Http\Requests\Categories;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * PATCH /api/categories/{category} — update global category.
 *
 * System categories: name/color only (type locked).
 */
class UpdateCategoryRequest extends FormRequest
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Category|null $category */
            $category = $this->route('category');

            if (! $category instanceof Category || ! $category->is_system) {
                return;
            }

            if (! $this->exists('type')) {
                return;
            }

            $nextType = (string) $this->input('type');
            if ($nextType !== '' && $nextType !== (string) $category->type) {
                $validator->errors()->add(
                    'type',
                    'O tipo de categorias do sistema não pode ser alterado.',
                );
            }
        });
    }
}

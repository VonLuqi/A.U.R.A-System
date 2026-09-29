<?php

namespace App\Http\Requests\Aliases;

use App\Enums\AliasMatchType;
use App\Models\TransactionAlias;
use App\Support\AliasRegex;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAliasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TransactionAlias::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = (int) $this->user()->id;

        return [
            'match_type' => ['required', 'string', Rule::in(AliasMatchType::values())],
            'match_pattern' => [
                'required',
                'string',
                'min:1',
                'max:255',
                Rule::unique('transaction_aliases', 'match_pattern')
                    ->where(fn ($q) => $q
                        ->where('user_id', $userId)
                        ->where('match_type', $this->input('match_type'))),
            ],
            'display_name' => ['required', 'string', 'min:1', 'max:255'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
            'apply_to_existing' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('match_type') !== AliasMatchType::Regex->value) {
                return;
            }

            $pattern = (string) $this->input('match_pattern', '');
            if ($pattern !== '' && ! AliasRegex::isValid($pattern)) {
                $validator->errors()->add('match_pattern', 'Padrão regex inválido.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'match_type.in' => 'Tipo de correspondência inválido.',
            'match_pattern.unique' => 'Já existe uma regra com este padrão e tipo.',
            'category_id.exists' => 'Categoria inválida.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'match_type' => $validated['match_type'],
            'match_pattern' => $validated['match_pattern'],
            'display_name' => $validated['display_name'],
            'category_id' => array_key_exists('category_id', $validated)
                ? ($validated['category_id'] !== null ? (int) $validated['category_id'] : null)
                : null,
            'priority' => isset($validated['priority']) ? (int) $validated['priority'] : 100,
            'is_active' => array_key_exists('is_active', $validated)
                ? (bool) $validated['is_active']
                : true,
        ];
    }

    public function applyToExisting(): bool
    {
        return $this->boolean('apply_to_existing');
    }
}

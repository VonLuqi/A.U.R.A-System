<?php

namespace App\Http\Requests\Aliases;

use App\Enums\AliasMatchType;
use App\Models\TransactionAlias;
use App\Support\AliasRegex;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAliasRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var TransactionAlias $alias */
        $alias = $this->route('alias');

        return $this->user()?->can('update', $alias) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var TransactionAlias $alias */
        $alias = $this->route('alias');
        $userId = (int) $this->user()->id;
        $matchType = $this->input('match_type', $alias->match_type?->value ?? $alias->match_type);

        return [
            'match_type' => ['sometimes', 'string', Rule::in(AliasMatchType::values())],
            'match_pattern' => [
                'sometimes',
                'string',
                'min:1',
                'max:255',
                Rule::unique('transaction_aliases', 'match_pattern')
                    ->ignore($alias->id)
                    ->where(fn ($q) => $q
                        ->where('user_id', $userId)
                        ->where('match_type', $matchType)),
            ],
            'display_name' => ['sometimes', 'string', 'min:1', 'max:255'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var TransactionAlias $alias */
            $alias = $this->route('alias');
            $type = $this->input('match_type', $alias->match_type?->value ?? $alias->match_type);
            $pattern = $this->input('match_pattern', $alias->match_pattern);

            if ($type !== AliasMatchType::Regex->value && $type !== AliasMatchType::Regex) {
                return;
            }

            if (is_string($pattern) && $pattern !== '' && ! AliasRegex::isValid($pattern)) {
                $validator->errors()->add('match_pattern', 'Padrão regex inválido.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $validated = $this->validated();
        $out = [];

        foreach (['match_type', 'match_pattern', 'display_name', 'priority', 'is_active'] as $key) {
            if (array_key_exists($key, $validated)) {
                $out[$key] = $validated[$key];
            }
        }

        if (array_key_exists('category_id', $validated)) {
            $out['category_id'] = $validated['category_id'] !== null
                ? (int) $validated['category_id']
                : null;
        }

        return $out;
    }
}

<?php

namespace App\Http\Requests\Aliases;

use App\Enums\AliasMatchType;
use App\Models\Transaction;
use App\Models\TransactionAlias;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RememberAliasRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Transaction $transaction */
        $transaction = $this->route('transaction');

        return ($this->user()?->can('view', $transaction) ?? false)
            && ($this->user()?->can('create', TransactionAlias::class) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'min:1', 'max:255'],
            'match_type' => ['sometimes', 'string', Rule::in([
                AliasMatchType::Exact->value,
                AliasMatchType::Contains->value,
            ])],
            'match_pattern' => ['sometimes', 'nullable', 'string', 'min:1', 'max:255'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'apply_to_existing' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(Transaction $transaction): array
    {
        $validated = $this->validated();
        $original = is_array($transaction->raw_payload)
            ? ($transaction->raw_payload['original_description'] ?? null)
            : null;
        $defaultPattern = is_string($original) && $original !== ''
            ? $original
            : (string) $transaction->description;

        return [
            'match_type' => $validated['match_type'] ?? AliasMatchType::Contains->value,
            'match_pattern' => isset($validated['match_pattern']) && is_string($validated['match_pattern']) && $validated['match_pattern'] !== ''
                ? $validated['match_pattern']
                : $defaultPattern,
            'display_name' => $validated['display_name'],
            'category_id' => array_key_exists('category_id', $validated)
                ? ($validated['category_id'] !== null ? (int) $validated['category_id'] : null)
                : ($transaction->category_id !== null ? (int) $transaction->category_id : null),
            'priority' => isset($validated['priority']) ? (int) $validated['priority'] : 100,
            'is_active' => true,
        ];
    }

    public function applyToExisting(): bool
    {
        return $this->boolean('apply_to_existing');
    }
}

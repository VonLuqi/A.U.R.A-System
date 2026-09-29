<?php

namespace App\Http\Requests\Aliases;

use App\Models\TransactionAlias;
use Illuminate\Foundation\Http\FormRequest;

class PreviewAliasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', TransactionAlias::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'min:1', 'max:500'],
        ];
    }
}

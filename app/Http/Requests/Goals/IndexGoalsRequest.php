<?php

namespace App\Http\Requests\Goals;

use App\Enums\GoalKind;
use App\Enums\GoalStatus;
use App\Models\Goal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexGoalsRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 50;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Goal::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', 'string', Rule::in(GoalStatus::values())],
            'kind' => ['sometimes', 'nullable', 'string', Rule::in(GoalKind::values())],
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['status', 'kind', 'q', 'per_page', 'page'] as $key) {
            if ($this->exists($key) && $this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }
    }

    /**
     * @return array{status: ?string, kind: ?string, q: ?string}
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'status' => $validated['status'] ?? null,
            'kind' => $validated['kind'] ?? null,
            'q' => $validated['q'] ?? null,
        ];
    }

    public function perPage(): int
    {
        $value = $this->validated('per_page');

        return $value !== null ? (int) $value : self::DEFAULT_PER_PAGE;
    }
}

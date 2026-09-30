<?php

namespace App\Http\Requests\Loans;

use App\Enums\LoanKind;
use App\Enums\LoanStatus;
use App\Models\Loan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexLoansRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 50;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Loan::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', 'string', Rule::in(LoanStatus::values())],
            'kind' => ['sometimes', 'nullable', 'string', Rule::in(LoanKind::values())],
            'due_from' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d'],
            'due_to' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:due_from'],
            'q' => ['sometimes', 'nullable', 'string', 'max:160'],
            'overdue' => ['sometimes', 'nullable', 'boolean'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['status', 'kind', 'due_from', 'due_to', 'q', 'overdue', 'per_page', 'page'] as $key) {
            if ($this->exists($key) && $this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }
    }

    /**
     * @return array{
     *     status: ?string,
     *     kind: ?string,
     *     due_from: ?string,
     *     due_to: ?string,
     *     q: ?string,
     *     overdue: ?bool
     * }
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'status' => $validated['status'] ?? null,
            'kind' => $validated['kind'] ?? null,
            'due_from' => $validated['due_from'] ?? null,
            'due_to' => $validated['due_to'] ?? null,
            'q' => $validated['q'] ?? null,
            'overdue' => array_key_exists('overdue', $validated)
                ? ($validated['overdue'] !== null ? (bool) $validated['overdue'] : null)
                : null,
        ];
    }

    public function perPage(): int
    {
        $value = $this->validated('per_page');

        return $value !== null ? (int) $value : self::DEFAULT_PER_PAGE;
    }
}

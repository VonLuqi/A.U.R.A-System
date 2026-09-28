<?php

namespace App\Http\Requests\Transactions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query params for GET /api/transactions (Etapa C §5.4.1).
 */
class IndexTransactionsRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 20;

    public const DEFAULT_SORT = 'occurred_on';

    public const DEFAULT_DIRECTION = 'desc';

    /** @var list<string> */
    public const SORTABLE = ['occurred_on', 'amount', 'created_at'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string|\Illuminate\Validation\Rules\Exists|\Illuminate\Validation\Rules\In>>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date', 'date_format:Y-m-d', 'before_or_equal:to'],
            'to' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:from'],
            'type' => ['nullable', 'string', Rule::in(['credit', 'debit'])],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'q' => ['nullable', 'string', 'max:120'],
            'statement_import_id' => ['nullable', 'integer', 'exists:statement_imports,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'string', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from.before_or_equal' => 'A data inicial deve ser anterior ou igual à data final.',
            'to.after_or_equal' => 'A data final deve ser posterior ou igual à data inicial.',
            'type.in' => 'O tipo deve ser credit ou debit.',
            'category_id.exists' => 'Categoria inválida.',
            'statement_import_id.exists' => 'Importação inválida.',
            'per_page.max' => 'O máximo de itens por página é 100.',
            'sort.in' => 'Ordenação inválida. Use occurred_on, amount ou created_at.',
            'direction.in' => 'Direção inválida. Use asc ou desc.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Allow empty query strings to become null so nullable rules pass cleanly.
        $nullable = ['from', 'to', 'type', 'category_id', 'q', 'statement_import_id', 'page', 'per_page', 'sort', 'direction'];

        $normalized = [];
        foreach ($nullable as $key) {
            if ($this->exists($key) && $this->input($key) === '') {
                $normalized[$key] = null;
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    public function fromDate(): ?string
    {
        $value = $this->validated('from');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function toDate(): ?string
    {
        $value = $this->validated('to');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function type(): ?string
    {
        $value = $this->validated('type');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function categoryId(): ?int
    {
        $value = $this->validated('category_id');

        return $value !== null ? (int) $value : null;
    }

    public function search(): ?string
    {
        $value = $this->validated('q');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function statementImportId(): ?int
    {
        $value = $this->validated('statement_import_id');

        return $value !== null ? (int) $value : null;
    }

    public function perPage(): int
    {
        $value = $this->validated('per_page');

        return $value !== null ? (int) $value : self::DEFAULT_PER_PAGE;
    }

    public function sort(): string
    {
        $value = $this->validated('sort');

        return is_string($value) && $value !== '' ? $value : self::DEFAULT_SORT;
    }

    public function direction(): string
    {
        $value = $this->validated('direction');

        return is_string($value) && $value !== '' ? $value : self::DEFAULT_DIRECTION;
    }

    /**
     * Normalized filters for TransactionQueryService (§5.4.2).
     *
     * @return array{
     *     from: ?string,
     *     to: ?string,
     *     type: ?string,
     *     category_id: ?int,
     *     q: ?string,
     *     statement_import_id: ?int,
     *     per_page: int,
     *     sort: string,
     *     direction: string
     * }
     */
    public function filters(): array
    {
        return [
            'from' => $this->fromDate(),
            'to' => $this->toDate(),
            'type' => $this->type(),
            'category_id' => $this->categoryId(),
            'q' => $this->search(),
            'statement_import_id' => $this->statementImportId(),
            'per_page' => $this->perPage(),
            'sort' => $this->sort(),
            'direction' => $this->direction(),
        ];
    }
}

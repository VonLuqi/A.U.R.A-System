<?php

namespace App\Http\Requests\Transactions;

use App\Http\Requests\Concerns\PreparesDateRangeQuery;
use App\Rules\AllTimeRequiresUnlimitedDateRange;
use App\Rules\WithinRoleDateRangeLimit;
use App\Support\DateRangeQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query params for GET /api/transactions (Etapa C §5.4.1 / PLAN_EXPANSAO §6.1).
 *
 * from/to: required together, or both omitted → current month (compat with analytics).
 * preset: current_month|last_30|last_90|all|custom|my_cycle
 * (custom exige from/to; all omite datas; my_cycle recalcula).
 * credit_card_ids + include_uncarded para multi-filtro de cartões.
 */
class IndexTransactionsRequest extends FormRequest
{
    use PreparesDateRangeQuery;

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
     * @return array<string, list<string|\Illuminate\Contracts\Validation\ValidationRule|\Illuminate\Validation\Rules\Exists|\Illuminate\Validation\Rules\In>>
     */
    public function rules(): array
    {
        $preset = DateRangeQuery::normalizePreset($this->input('preset'));
        $customRequiresDates = $preset === DateRangeQuery::PRESET_CUSTOM;
        $isAll = $preset === DateRangeQuery::PRESET_ALL;
        $userId = $this->user()?->id;
        $ownedCard = Rule::exists('credit_cards', 'id')->where(
            fn ($query) => $userId !== null ? $query->where('user_id', $userId) : $query->whereRaw('0=1')
        );

        return [
            'preset' => [
                'nullable',
                'string',
                Rule::in(DateRangeQuery::PRESETS),
                new AllTimeRequiresUnlimitedDateRange($this->user()),
            ],
            'cycle_offset' => ['nullable', 'integer', 'min:-120', 'max:120'],
            'from' => [
                $customRequiresDates ? 'required' : 'nullable',
                $isAll ? 'nullable' : 'required_with:to',
                'date',
                'date_format:Y-m-d',
                'before_or_equal:to',
            ],
            'to' => [
                $customRequiresDates ? 'required' : 'nullable',
                $isAll ? 'nullable' : 'required_with:from',
                'date',
                'date_format:Y-m-d',
                'after_or_equal:from',
                new WithinRoleDateRangeLimit($this->user()),
            ],
            'type' => ['nullable', 'string', Rule::in(['credit', 'debit'])],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'credit_card_id' => ['nullable', 'integer', $ownedCard],
            'credit_card_ids' => ['nullable', 'array'],
            'credit_card_ids.*' => ['integer', $ownedCard],
            'include_uncarded' => ['nullable', 'boolean'],
            'loan_id' => ['nullable', 'integer', 'exists:loans,id'],
            'debtor_id' => ['nullable', 'integer', 'exists:debtors,id'],
            'has_loan' => ['nullable', 'boolean'],
            'has_credit_card' => ['nullable', 'boolean'],
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
            'from.required' => 'preset=custom exige from e to.',
            'to.required' => 'preset=custom exige from e to.',
            'from.before_or_equal' => 'A data inicial deve ser anterior ou igual à data final.',
            'to.after_or_equal' => 'A data final deve ser posterior ou igual à data inicial.',
            'type.in' => 'O tipo deve ser credit ou debit.',
            'category_id.exists' => 'Categoria inválida.',
            'credit_card_id.exists' => 'Cartão inválido.',
            'credit_card_ids.*.exists' => 'Cartão inválido.',
            'statement_import_id.exists' => 'Importação inválida.',
            'per_page.max' => 'O máximo de itens por página é 100.',
            'sort.in' => 'Ordenação inválida. Use occurred_on, amount ou created_at.',
            'direction.in' => 'Direção inválida. Use asc ou desc.',
            'from.required_with' => 'Informe from e to juntos, ou omita ambos para o mês corrente.',
            'to.required_with' => 'Informe from e to juntos, ou omita ambos para o mês corrente.',
            'preset.in' => 'preset inválido. Use current_month, last_30, last_90, all, custom ou my_cycle.',
            'to' => 'O intervalo de datas excede o limite do seu perfil.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeEmptyDateRangeInputs([
            'type', 'category_id', 'credit_card_id', 'loan_id', 'debtor_id', 'has_loan', 'has_credit_card',
            'q', 'statement_import_id', 'page', 'per_page', 'sort', 'direction', 'include_uncarded',
        ]);
        $this->normalizeCreditCardIdFilters();
        $this->applyDateRangePresetOrDefault();
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

    public function creditCardId(): ?int
    {
        $ids = $this->creditCardIds();
        if (count($ids) === 1) {
            return $ids[0];
        }

        $value = $this->validated('credit_card_id');

        return $value !== null ? (int) $value : null;
    }

    /**
     * @return list<int>
     */
    public function creditCardIds(): array
    {
        $ids = $this->validated('credit_card_ids') ?? [];

        if (! is_array($ids) || $ids === []) {
            $singular = $this->validated('credit_card_id');

            return $singular !== null ? [(int) $singular] : [];
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    public function includeUncarded(): bool
    {
        if (! $this->exists('include_uncarded') || $this->input('include_uncarded') === null) {
            return false;
        }

        return $this->boolean('include_uncarded');
    }

    public function loanId(): ?int
    {
        $value = $this->validated('loan_id');

        return $value !== null ? (int) $value : null;
    }

    public function debtorId(): ?int
    {
        $value = $this->validated('debtor_id');

        return $value !== null ? (int) $value : null;
    }

    public function hasLoan(): ?bool
    {
        if (! $this->exists('has_loan') || $this->input('has_loan') === null || $this->input('has_loan') === '') {
            return null;
        }

        return $this->boolean('has_loan');
    }

    public function hasCreditCard(): ?bool
    {
        if (! $this->exists('has_credit_card') || $this->input('has_credit_card') === null || $this->input('has_credit_card') === '') {
            return null;
        }

        return $this->boolean('has_credit_card');
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

    public function preset(): ?string
    {
        return DateRangeQuery::normalizePreset($this->validated('preset') ?? $this->input('preset'));
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
     *     credit_card_id: ?int,
     *     credit_card_ids: list<int>,
     *     include_uncarded: bool,
     *     loan_id: ?int,
     *     debtor_id: ?int,
     *     has_loan: ?bool,
     *     has_credit_card: ?bool,
     *     per_page: int,
     *     sort: string,
     *     direction: string,
     *     preset: ?string
     * }
     */
    public function filters(): array
    {
        $ids = $this->creditCardIds();

        return [
            'from' => $this->fromDate(),
            'to' => $this->toDate(),
            'type' => $this->type(),
            'category_id' => $this->categoryId(),
            'q' => $this->search(),
            'statement_import_id' => $this->statementImportId(),
            'credit_card_id' => count($ids) === 1 ? $ids[0] : null,
            'credit_card_ids' => $ids,
            'include_uncarded' => $this->includeUncarded(),
            'loan_id' => $this->loanId(),
            'debtor_id' => $this->debtorId(),
            'has_loan' => $this->hasLoan(),
            'has_credit_card' => $this->hasCreditCard(),
            'per_page' => $this->perPage(),
            'sort' => $this->sort(),
            'direction' => $this->direction(),
            'preset' => $this->preset(),
        ];
    }
}

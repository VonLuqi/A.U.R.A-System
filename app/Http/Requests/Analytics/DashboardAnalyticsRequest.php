<?php

namespace App\Http\Requests\Analytics;

use App\Http\Requests\Concerns\PreparesDateRangeQuery;
use App\Rules\WithinRoleDateRangeLimit;
use App\Support\DateRangeQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query params for GET /api/analytics/dashboard (Etapa C §5.5.1 / PLAN_EXPANSAO §6.1).
 *
 * from/to: required together, or both omitted → current month in APP_TIMEZONE.
 * preset: current_month|last_30|last_90|custom (custom exige from/to).
 * group_by: optional; when omitted → DateRangeQuery::resolveGroupBy (≤45 days → day).
 */
class DashboardAnalyticsRequest extends FormRequest
{
    use PreparesDateRangeQuery;

    /** @var list<string> */
    public const GROUP_BY = ['day', 'month'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string|\Illuminate\Validation\Rules\Exists|\Illuminate\Validation\Rules\In|\App\Rules\WithinRoleDateRangeLimit>>
     */
    public function rules(): array
    {
        $preset = DateRangeQuery::normalizePreset($this->input('preset'));
        $customRequiresDates = $preset === DateRangeQuery::PRESET_CUSTOM;

        return [
            'preset' => ['nullable', 'string', Rule::in(DateRangeQuery::PRESETS)],
            'from' => [
                $customRequiresDates ? 'required' : 'nullable',
                'required_with:to',
                'date',
                'date_format:Y-m-d',
                'before_or_equal:to',
            ],
            'to' => [
                $customRequiresDates ? 'required' : 'nullable',
                'required_with:from',
                'date',
                'date_format:Y-m-d',
                'after_or_equal:from',
                new WithinRoleDateRangeLimit($this->user()),
            ],
            'type' => ['nullable', 'string', Rule::in(['credit', 'debit'])],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'credit_card_id' => ['nullable', 'integer', 'exists:credit_cards,id'],
            'debtor_id' => ['nullable', 'integer', 'exists:debtors,id'],
            'q' => ['nullable', 'string', 'max:120'],
            'group_by' => ['nullable', 'string', Rule::in(self::GROUP_BY)],
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
            'from.required_with' => 'Informe from e to juntos, ou omita ambos para o mês corrente.',
            'to.required_with' => 'Informe from e to juntos, ou omita ambos para o mês corrente.',
            'from.before_or_equal' => 'A data inicial deve ser anterior ou igual à data final.',
            'to.after_or_equal' => 'A data final deve ser posterior ou igual à data inicial.',
            'type.in' => 'O tipo deve ser credit ou debit.',
            'category_id.exists' => 'Categoria inválida.',
            'credit_card_id.exists' => 'Cartão inválido.',
            'debtor_id.exists' => 'Pessoa inválida.',
            'group_by.in' => 'group_by inválido. Use day ou month.',
            'preset.in' => 'preset inválido. Use current_month, last_30, last_90 ou custom.',
            'to' => 'O intervalo de datas excede o limite do seu perfil.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeEmptyDateRangeInputs([
            'type', 'category_id', 'credit_card_id', 'debtor_id', 'q', 'group_by',
        ]);
        $this->applyDateRangePresetOrDefault();
        $this->applyResolvedGroupBy();
    }

    /**
     * @return array{0: string, 1: string} [from, to] Y-m-d
     */
    public static function currentMonthBounds(?\DateTimeInterface $at = null): array
    {
        return DateRangeQuery::currentMonthBounds($at);
    }

    public function fromDate(): string
    {
        return (string) $this->validated('from');
    }

    public function toDate(): string
    {
        return (string) $this->validated('to');
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

    public function creditCardId(): ?int
    {
        $value = $this->validated('credit_card_id');

        return $value !== null ? (int) $value : null;
    }

    public function debtorId(): ?int
    {
        $value = $this->validated('debtor_id');

        return $value !== null ? (int) $value : null;
    }

    public function search(): ?string
    {
        $value = $this->validated('q');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function groupBy(): string
    {
        $value = $this->validated('group_by');

        if (is_string($value) && in_array($value, self::GROUP_BY, true)) {
            return $value;
        }

        return DateRangeQuery::resolveGroupBy($this->fromDate(), $this->toDate());
    }

    public function preset(): ?string
    {
        return DateRangeQuery::normalizePreset($this->validated('preset') ?? $this->input('preset'));
    }

    /**
     * Filters compatible with TransactionQueryService (+ group_by for series).
     *
     * @return array{
     *     from: string,
     *     to: string,
     *     type: ?string,
     *     category_id: ?int,
     *     credit_card_id: ?int,
     *     debtor_id: ?int,
     *     q: ?string,
     *     group_by: string,
     *     preset: ?string
     * }
     */
    public function filters(): array
    {
        return [
            'from' => $this->fromDate(),
            'to' => $this->toDate(),
            'type' => $this->type(),
            'category_id' => $this->categoryId(),
            'credit_card_id' => $this->creditCardId(),
            'debtor_id' => $this->debtorId(),
            'q' => $this->search(),
            'group_by' => $this->groupBy(),
            'preset' => $this->preset(),
        ];
    }
}

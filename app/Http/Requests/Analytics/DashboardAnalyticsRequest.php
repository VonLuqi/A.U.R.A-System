<?php

namespace App\Http\Requests\Analytics;

use App\Http\Requests\Concerns\PreparesDateRangeQuery;
use App\Rules\AllTimeRequiresUnlimitedDateRange;
use App\Rules\WithinRoleDateRangeLimit;
use App\Support\DateRangeQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query params for GET /api/analytics/dashboard (Etapa C §5.5.1 / PLAN_EXPANSAO §6.1).
 *
 * from/to: required together, or both omitted → current month in APP_TIMEZONE.
 * preset: current_month|last_30|last_90|all|custom|my_cycle|card_cycle
 * (custom exige from/to; all omite datas; cycles recalculam; card_cycle exige credit_card_id).
 * group_by: optional; when omitted → DateRangeQuery::resolveGroupBy (≤45 days → day); all → month.
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
     * @return array<string, list<string|\Illuminate\Contracts\Validation\ValidationRule|\Illuminate\Validation\Rules\Exists|\Illuminate\Validation\Rules\In>>
     */
    public function rules(): array
    {
        $preset = DateRangeQuery::normalizePreset($this->input('preset'));
        $customRequiresDates = $preset === DateRangeQuery::PRESET_CUSTOM;
        $isAll = $preset === DateRangeQuery::PRESET_ALL;
        $isCardCycle = $preset === DateRangeQuery::PRESET_CARD_CYCLE;
        $userId = $this->user()?->id;

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
            'credit_card_id' => [
                $isCardCycle ? 'required' : 'nullable',
                'integer',
                Rule::exists('credit_cards', 'id')->where(
                    fn ($query) => $userId !== null ? $query->where('user_id', $userId) : $query->whereRaw('0=1')
                ),
            ],
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
            'credit_card_id.required' => 'Selecione um cartão para o ciclo do cartão.',
            'credit_card_id.exists' => 'Cartão inválido.',
            'debtor_id.exists' => 'Pessoa inválida.',
            'group_by.in' => 'group_by inválido. Use day ou month.',
            'preset.in' => 'preset inválido. Use current_month, last_30, last_90, all, custom, my_cycle ou card_cycle.',
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

        if ($this->preset() === DateRangeQuery::PRESET_ALL) {
            return 'month';
        }

        $from = $this->fromDate();
        $to = $this->toDate();

        if ($from === null || $to === null) {
            return 'month';
        }

        return DateRangeQuery::resolveGroupBy($from, $to);
    }

    public function preset(): ?string
    {
        return DateRangeQuery::normalizePreset($this->validated('preset') ?? $this->input('preset'));
    }

    /**
     * Filters compatible with TransactionQueryService (+ group_by for series).
     *
     * @return array{
     *     from: ?string,
     *     to: ?string,
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

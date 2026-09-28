<?php

namespace App\Http\Requests\Analytics;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query params for GET /api/analytics/dashboard (Etapa C §5.5.1).
 *
 * from/to: required together, or both omitted → current month in APP_TIMEZONE.
 */
class DashboardAnalyticsRequest extends FormRequest
{
    public const DEFAULT_GROUP_BY = 'month';

    /** @var list<string> */
    public const GROUP_BY = ['day', 'month'];

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
            'from' => ['nullable', 'required_with:to', 'date', 'date_format:Y-m-d', 'before_or_equal:to'],
            'to' => ['nullable', 'required_with:from', 'date', 'date_format:Y-m-d', 'after_or_equal:from'],
            'type' => ['nullable', 'string', Rule::in(['credit', 'debit'])],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
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
            'from.required_with' => 'Informe from e to juntos, ou omita ambos para o mês corrente.',
            'to.required_with' => 'Informe from e to juntos, ou omita ambos para o mês corrente.',
            'from.before_or_equal' => 'A data inicial deve ser anterior ou igual à data final.',
            'to.after_or_equal' => 'A data final deve ser posterior ou igual à data inicial.',
            'type.in' => 'O tipo deve ser credit ou debit.',
            'category_id.exists' => 'Categoria inválida.',
            'group_by.in' => 'group_by inválido. Use day ou month.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['from', 'to', 'type', 'category_id', 'q', 'group_by'];
        $normalized = [];

        foreach ($nullable as $key) {
            if ($this->exists($key) && $this->input($key) === '') {
                $normalized[$key] = null;
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }

        $from = $this->input('from');
        $to = $this->input('to');

        if (($from === null || $from === '') && ($to === null || $to === '')) {
            [$defaultFrom, $defaultTo] = self::currentMonthBounds();
            $this->merge([
                'from' => $defaultFrom,
                'to' => $defaultTo,
            ]);
        }

        if (! $this->filled('group_by')) {
            $this->merge(['group_by' => self::DEFAULT_GROUP_BY]);
        }
    }

    /**
     * @return array{0: string, 1: string} [from, to] Y-m-d
     */
    public static function currentMonthBounds(?\DateTimeInterface $at = null): array
    {
        $now = CarbonImmutable::instance(
            $at ?? CarbonImmutable::now(config('app.timezone'))
        )->timezone(config('app.timezone'));

        return [
            $now->startOfMonth()->format('Y-m-d'),
            $now->endOfMonth()->format('Y-m-d'),
        ];
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

    public function search(): ?string
    {
        $value = $this->validated('q');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function groupBy(): string
    {
        $value = $this->validated('group_by');

        return is_string($value) && $value !== '' ? $value : self::DEFAULT_GROUP_BY;
    }

    /**
     * Filters compatible with TransactionQueryService (+ group_by for series).
     *
     * @return array{
     *     from: string,
     *     to: string,
     *     type: ?string,
     *     category_id: ?int,
     *     q: ?string,
     *     group_by: string
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
            'group_by' => $this->groupBy(),
        ];
    }
}

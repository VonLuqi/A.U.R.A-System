<?php

namespace App\Http\Requests\Concerns;

use App\Models\CreditCard;
use App\Support\DateRangeQuery;

/**
 * Shared prepareForValidation for from/to/preset (+ optional group_by) — PLAN_EXPANSAO §6.1.
 */
trait PreparesDateRangeQuery
{
    /**
     * @param  list<string>  $extraNullable
     */
    protected function normalizeEmptyDateRangeInputs(array $extraNullable = []): void
    {
        $nullable = array_merge(['from', 'to', 'preset', 'cycle_offset'], $extraNullable);
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

    /**
     * Apply preset bounds or default to current month when both dates omitted.
     * Named presets overwrite from/to. all clears dates. custom leaves dates for validation.
     * Cycle presets resolve day from user prefs or selected credit card + type.
     */
    protected function applyDateRangePresetOrDefault(): void
    {
        $preset = DateRangeQuery::normalizePreset($this->input('preset'));

        if ($preset === DateRangeQuery::PRESET_ALL) {
            $this->merge([
                'from' => null,
                'to' => null,
                'preset' => $preset,
                'cycle_offset' => null,
            ]);

            return;
        }

        if (DateRangeQuery::isCyclePreset($preset)) {
            $offset = $this->normalizedCycleOffset();
            $day = $this->resolveCycleDay($preset);

            if ($day !== null) {
                $bounds = DateRangeQuery::cycleDayBounds($day, $offset);
                $this->merge([
                    'from' => $bounds[0],
                    'to' => $bounds[1],
                    'preset' => $preset,
                    'cycle_offset' => $offset,
                ]);
            } else {
                $this->merge([
                    'preset' => $preset,
                    'cycle_offset' => $offset,
                ]);
            }

            return;
        }

        if ($preset !== null && $preset !== DateRangeQuery::PRESET_CUSTOM) {
            $bounds = DateRangeQuery::boundsForPreset($preset);
            if ($bounds !== null) {
                $this->merge([
                    'from' => $bounds[0],
                    'to' => $bounds[1],
                    'preset' => $preset,
                    'cycle_offset' => null,
                ]);
            }

            return;
        }

        $from = $this->input('from');
        $to = $this->input('to');
        $bothEmpty = ($from === null || $from === '') && ($to === null || $to === '');

        if ($bothEmpty) {
            // custom without dates → leave empty so validation 422s;
            // no preset / omitted → current month (compat).
            if ($preset === DateRangeQuery::PRESET_CUSTOM) {
                return;
            }

            [$defaultFrom, $defaultTo] = DateRangeQuery::currentMonthBounds();
            $this->merge([
                'from' => $defaultFrom,
                'to' => $defaultTo,
            ]);
        }
    }

    protected function normalizedCycleOffset(): int
    {
        if (! $this->exists('cycle_offset') || $this->input('cycle_offset') === null) {
            return 0;
        }

        return max(-120, min(120, (int) $this->input('cycle_offset')));
    }

    /**
     * @return int|null Null when card_cycle cannot resolve (missing/foreign card).
     */
    protected function resolveCycleDay(string $preset): ?int
    {
        $type = $this->input('type');
        $type = is_string($type) && $type !== '' ? $type : null;

        if ($preset === DateRangeQuery::PRESET_MY_CYCLE) {
            $user = $this->user();
            if ($user === null) {
                return DateRangeQuery::DEFAULT_EXPENSE_CYCLE_DAY;
            }

            return DateRangeQuery::cycleDayForType(
                $type,
                $user->expenseCycleDay(),
                $user->incomeCycleDay(),
            );
        }

        if ($preset === DateRangeQuery::PRESET_CARD_CYCLE) {
            $user = $this->user();
            $cardId = $this->input('credit_card_id');

            if ($user === null || $cardId === null || $cardId === '') {
                return null;
            }

            $card = CreditCard::query()
                ->where('user_id', $user->id)
                ->whereKey((int) $cardId)
                ->first(['id', 'closing_day', 'due_day']);

            if ($card === null) {
                return null;
            }

            return DateRangeQuery::cycleDayForType(
                $type,
                (int) $card->closing_day,
                (int) $card->due_day,
            );
        }

        return null;
    }

    /**
     * When group_by omitted, derive from span (≤45 days → day). Explicit client value wins.
     * preset=all → month (unbounded history).
     */
    protected function applyResolvedGroupBy(): void
    {
        if ($this->filled('group_by')) {
            return;
        }

        $preset = DateRangeQuery::normalizePreset($this->input('preset'));
        if ($preset === DateRangeQuery::PRESET_ALL) {
            $this->merge(['group_by' => 'month']);

            return;
        }

        $from = $this->input('from');
        $to = $this->input('to');

        if (! is_string($from) || $from === '' || ! is_string($to) || $to === '') {
            return;
        }

        $this->merge([
            'group_by' => DateRangeQuery::resolveGroupBy($from, $to),
        ]);
    }
}

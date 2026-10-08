<?php

namespace App\Http\Requests\Concerns;

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
     * Normalize credit_card_ids (array or comma string) + legacy credit_card_id.
     */
    protected function normalizeCreditCardIdFilters(): void
    {
        $ids = $this->input('credit_card_ids');

        if (is_string($ids)) {
            $parsed = [];
            foreach (explode(',', $ids) as $part) {
                $part = trim($part);
                if ($part !== '' && ctype_digit($part)) {
                    $parsed[] = (int) $part;
                }
            }
            $this->merge(['credit_card_ids' => array_values(array_unique($parsed))]);
            $ids = $this->input('credit_card_ids');
        }

        if ((! is_array($ids) || $ids === []) && $this->filled('credit_card_id')) {
            $this->merge(['credit_card_ids' => [(int) $this->input('credit_card_id')]]);
        }

        if ($this->exists('include_uncarded') && $this->input('include_uncarded') === '') {
            $this->merge(['include_uncarded' => null]);
        }
    }

    /**
     * Apply preset bounds or default to current month when both dates omitted.
     * Named presets overwrite from/to. all clears dates. custom leaves dates for validation.
     * my_cycle resolves day from user prefs + type.
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

        if ($preset === DateRangeQuery::PRESET_MY_CYCLE) {
            $offset = $this->normalizedCycleOffset();
            $day = $this->resolveMyCycleDay();
            $bounds = DateRangeQuery::cycleDayBounds($day, $offset);
            $this->merge([
                'from' => $bounds[0],
                'to' => $bounds[1],
                'preset' => $preset,
                'cycle_offset' => $offset,
            ]);

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

    protected function resolveMyCycleDay(): int
    {
        $type = $this->input('type');
        $type = is_string($type) && $type !== '' ? $type : null;
        $user = $this->user();

        if ($user === null) {
            return DateRangeQuery::cycleDayForType(
                $type,
                DateRangeQuery::DEFAULT_EXPENSE_CYCLE_DAY,
                DateRangeQuery::DEFAULT_INCOME_CYCLE_DAY,
            );
        }

        return DateRangeQuery::cycleDayForType(
            $type,
            $user->expenseCycleDay(),
            $user->incomeCycleDay(),
        );
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

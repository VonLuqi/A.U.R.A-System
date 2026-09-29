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
        $nullable = array_merge(['from', 'to', 'preset'], $extraNullable);
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
     * Named presets overwrite from/to. custom leaves dates for validation.
     */
    protected function applyDateRangePresetOrDefault(): void
    {
        $preset = DateRangeQuery::normalizePreset($this->input('preset'));

        if ($preset !== null && $preset !== DateRangeQuery::PRESET_CUSTOM) {
            $bounds = DateRangeQuery::boundsForPreset($preset);
            if ($bounds !== null) {
                $this->merge([
                    'from' => $bounds[0],
                    'to' => $bounds[1],
                    'preset' => $preset,
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

    /**
     * When group_by omitted, derive from span (≤45 days → day). Explicit client value wins.
     */
    protected function applyResolvedGroupBy(): void
    {
        if ($this->filled('group_by')) {
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

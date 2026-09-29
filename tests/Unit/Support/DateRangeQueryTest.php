<?php

namespace Tests\Unit\Support;

use App\Support\DateRangeQuery;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * PLAN_EXPANSAO §6.1 — DateRangeQuery presets + resolveGroupBy.
 */
class DateRangeQueryTest extends TestCase
{
    public function test_current_month_and_last_n_days_bounds(): void
    {
        config(['app.timezone' => 'America/Sao_Paulo']);
        $at = CarbonImmutable::parse('2026-09-15 12:00:00', 'America/Sao_Paulo');

        $this->assertSame(
            ['2026-09-01', '2026-09-30'],
            DateRangeQuery::currentMonthBounds($at)
        );
        $this->assertSame(
            ['2026-08-17', '2026-09-15'],
            DateRangeQuery::lastNDaysBounds(30, $at)
        );
        $this->assertSame(
            ['2026-06-18', '2026-09-15'],
            DateRangeQuery::lastNDaysBounds(90, $at)
        );
    }

    public function test_bounds_for_preset(): void
    {
        config(['app.timezone' => 'America/Sao_Paulo']);
        $at = CarbonImmutable::parse('2026-09-15 12:00:00', 'America/Sao_Paulo');

        $this->assertSame(
            ['2026-09-01', '2026-09-30'],
            DateRangeQuery::boundsForPreset('current_month', $at)
        );
        $this->assertNull(DateRangeQuery::boundsForPreset('custom', $at));
    }

    public function test_resolve_group_by_threshold_45_days(): void
    {
        $this->assertSame('day', DateRangeQuery::resolveGroupBy('2026-09-01', '2026-09-30'));
        $this->assertSame('day', DateRangeQuery::resolveGroupBy('2026-08-01', '2026-09-14')); // 45 days
        $this->assertSame('month', DateRangeQuery::resolveGroupBy('2026-08-01', '2026-09-15')); // 46 days
        $this->assertSame('month', DateRangeQuery::resolveGroupBy('2026-01-01', '2026-03-31'));
    }

    public function test_inclusive_day_span(): void
    {
        $this->assertSame(1, DateRangeQuery::inclusiveDaySpan('2026-09-01', '2026-09-01'));
        $this->assertSame(90, DateRangeQuery::inclusiveDaySpan('2026-01-01', '2026-03-31'));
        $this->assertSame(0, DateRangeQuery::inclusiveDaySpan('2026-09-30', '2026-09-01'));
    }
}

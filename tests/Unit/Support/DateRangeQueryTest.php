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
        $this->assertNull(DateRangeQuery::boundsForPreset('all', $at));
    }

    public function test_all_is_allowed_preset(): void
    {
        $this->assertTrue(DateRangeQuery::isAllowedPreset('all'));
        $this->assertSame('all', DateRangeQuery::normalizePreset('all'));
        $this->assertContains(DateRangeQuery::PRESET_ALL, DateRangeQuery::PRESETS);
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

    public function test_cycle_day_bounds_inclusive_and_offset(): void
    {
        config(['app.timezone' => 'America/Sao_Paulo']);
        $at = CarbonImmutable::parse('2026-10-07 12:00:00', 'America/Sao_Paulo');

        $this->assertSame(
            ['2026-10-06', '2026-11-06'],
            DateRangeQuery::cycleDayBounds(6, 0, $at)
        );
        $this->assertSame(
            ['2026-09-06', '2026-10-06'],
            DateRangeQuery::cycleDayBounds(6, -1, $at)
        );

        $beforeClose = CarbonImmutable::parse('2026-10-05 12:00:00', 'America/Sao_Paulo');
        $this->assertSame(
            ['2026-09-06', '2026-10-06'],
            DateRangeQuery::cycleDayBounds(6, 0, $beforeClose)
        );
    }

    public function test_cycle_day_bounds_clamps_february(): void
    {
        config(['app.timezone' => 'America/Sao_Paulo']);
        $at = CarbonImmutable::parse('2026-02-15 12:00:00', 'America/Sao_Paulo');

        $this->assertSame(
            ['2026-01-31', '2026-02-28'],
            DateRangeQuery::cycleDayBounds(31, 0, $at)
        );
    }

    public function test_cycle_day_for_type(): void
    {
        $this->assertSame(6, DateRangeQuery::cycleDayForType(null, 6, 12));
        $this->assertSame(6, DateRangeQuery::cycleDayForType('debit', 6, 12));
        $this->assertSame(12, DateRangeQuery::cycleDayForType('credit', 6, 12));
    }

    public function test_my_cycle_bounds_unions_expense_and_income_for_all(): void
    {
        config(['app.timezone' => 'America/Sao_Paulo']);
        $at = CarbonImmutable::parse('2026-10-07 12:00:00', 'America/Sao_Paulo');

        $this->assertSame(
            ['2026-10-06', '2026-11-06'],
            DateRangeQuery::myCycleBounds('debit', 6, 12, 0, $at)
        );
        $this->assertSame(
            ['2026-09-12', '2026-10-12'],
            DateRangeQuery::myCycleBounds('credit', 6, 12, 0, $at)
        );
        $this->assertSame(
            ['2026-10-06', '2026-11-12'],
            DateRangeQuery::myCycleBounds(null, 6, 12, 0, $at)
        );
        $this->assertSame(
            ['2026-09-06', '2026-10-12'],
            DateRangeQuery::myCycleBounds(null, 6, 12, -1, $at)
        );

        $ranges = DateRangeQuery::myCycleTypeRanges(null, 6, 12, -1, $at);
        $this->assertSame(['2026-09-06', '2026-10-06'], $ranges['debit']);
        $this->assertSame(['2026-09-12', '2026-10-12'], $ranges['credit']);
        $this->assertNull(DateRangeQuery::myCycleTypeRanges('debit', 6, 12, -1, $at));
        $this->assertNull(DateRangeQuery::myCycleTypeRanges(null, 6, 6, -1, $at));
    }
}

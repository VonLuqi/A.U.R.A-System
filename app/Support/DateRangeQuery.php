<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Shared date-range helpers for analytics / transactions query (PLAN_EXPANSAO §6.1).
 *
 * Mirrors resources/js/lib/dates.js: presets + resolveGroupBy (≤45 days → day).
 */
final class DateRangeQuery
{
    public const PRESET_CURRENT_MONTH = 'current_month';

    public const PRESET_LAST_30 = 'last_30';

    public const PRESET_LAST_90 = 'last_90';

    public const PRESET_ALL = 'all';

    public const PRESET_CUSTOM = 'custom';

    public const PRESET_MY_CYCLE = 'my_cycle';

    /** Inclusive day span at or below this → series group_by=day. */
    public const GROUP_BY_DAY_MAX_DAYS = 45;

    public const DEFAULT_EXPENSE_CYCLE_DAY = 6;

    public const DEFAULT_INCOME_CYCLE_DAY = 12;

    /** @var list<string> */
    public const PRESETS = [
        self::PRESET_CURRENT_MONTH,
        self::PRESET_LAST_30,
        self::PRESET_LAST_90,
        self::PRESET_ALL,
        self::PRESET_CUSTOM,
        self::PRESET_MY_CYCLE,
    ];

    /** Named presets that compute from/to without extra context (not custom / all / cycles). */
    /** @var list<string> */
    public const COMPUTED_PRESETS = [
        self::PRESET_CURRENT_MONTH,
        self::PRESET_LAST_30,
        self::PRESET_LAST_90,
        self::PRESET_ALL,
    ];

    /** @var list<string> */
    public const CYCLE_PRESETS = [
        self::PRESET_MY_CYCLE,
    ];

    /**
     * @return array{0: string, 1: string} [from, to] Y-m-d
     */
    public static function currentMonthBounds(?DateTimeInterface $at = null): array
    {
        $now = self::now($at);

        return [
            $now->startOfMonth()->format('Y-m-d'),
            $now->endOfMonth()->format('Y-m-d'),
        ];
    }

    /**
     * Inclusive last N calendar days ending today (APP_TIMEZONE).
     *
     * @return array{0: string, 1: string} [from, to] Y-m-d
     */
    public static function lastNDaysBounds(int $days, ?DateTimeInterface $at = null): array
    {
        $days = max(1, $days);
        $to = self::now($at)->startOfDay();
        $from = $to->subDays($days - 1);

        return [
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
        ];
    }

    /**
     * Inclusive cycle from day-of-month D to the next month's D (clamped).
     * Offset 0 = cycle containing `$at`; negative = previous cycles.
     *
     * @return array{0: string, 1: string} [from, to] Y-m-d
     */
    public static function cycleDayBounds(int $day, int $offset = 0, ?DateTimeInterface $at = null): array
    {
        $now = self::now($at)->startOfDay();
        $day = max(1, min(31, $day));
        $offset = max(-120, min(120, $offset));

        $start = self::dateOnMonth($now->year, $now->month, $day);
        if ($start->gt($now)) {
            $prev = $now->subMonthNoOverflow()->startOfMonth();
            $start = self::dateOnMonth($prev->year, $prev->month, $day);
        }

        if ($offset !== 0) {
            $shifted = $start->addMonthsNoOverflow($offset)->startOfMonth();
            $start = self::dateOnMonth($shifted->year, $shifted->month, $day);
        }

        $endMonth = $start->addMonthNoOverflow()->startOfMonth();
        $end = self::dateOnMonth($endMonth->year, $endMonth->month, $day);

        return [
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
        ];
    }

    /**
     * Day for my_cycle given transaction type filter.
     * credit → income day; debit → expense day; empty/all → expense day (navigation anchor).
     */
    public static function cycleDayForType(?string $type, int $expenseOrClosingDay, int $incomeOrDueDay): int
    {
        if ($type === 'credit') {
            return max(1, min(31, $incomeOrDueDay));
        }

        return max(1, min(31, $expenseOrClosingDay));
    }

    /**
     * Bounds for my_cycle.
     * debit → expense day only; credit → income day only;
     * empty/all → union of expense cycle + income cycle aligned to the expense start month
     * (so saídas day 6 + entradas day 12 cover both windows in the same navigated period).
     *
     * @return array{0: string, 1: string} [from, to] Y-m-d
     */
    public static function myCycleBounds(
        ?string $type,
        int $expenseDay,
        int $incomeDay,
        int $offset = 0,
        ?DateTimeInterface $at = null,
    ): array {
        $expenseDay = max(1, min(31, $expenseDay));
        $incomeDay = max(1, min(31, $incomeDay));

        if ($type === 'credit') {
            return self::cycleDayBounds($incomeDay, $offset, $at);
        }

        if ($type === 'debit') {
            return self::cycleDayBounds($expenseDay, $offset, $at);
        }

        [$expenseFrom, $expenseTo] = self::cycleDayBounds($expenseDay, $offset, $at);
        [$incomeFrom, $incomeTo] = self::incomeBoundsAlignedToExpenseStart(
            $expenseFrom,
            $incomeDay,
        );

        $from = $expenseFrom <= $incomeFrom ? $expenseFrom : $incomeFrom;
        $to = $expenseTo >= $incomeTo ? $expenseTo : $incomeTo;

        return [$from, $to];
    }

    /**
     * When type is empty and days differ, per-type windows for filtering
     * (debit in expense cycle, credit in aligned income cycle). Null when not needed.
     *
     * @return array{debit: array{0: string, 1: string}, credit: array{0: string, 1: string}}|null
     */
    public static function myCycleTypeRanges(
        ?string $type,
        int $expenseDay,
        int $incomeDay,
        int $offset = 0,
        ?DateTimeInterface $at = null,
    ): ?array {
        if ($type === 'credit' || $type === 'debit') {
            return null;
        }

        $expenseDay = max(1, min(31, $expenseDay));
        $incomeDay = max(1, min(31, $incomeDay));

        if ($expenseDay === $incomeDay) {
            return null;
        }

        [$expenseFrom, $expenseTo] = self::cycleDayBounds($expenseDay, $offset, $at);
        [$incomeFrom, $incomeTo] = self::incomeBoundsAlignedToExpenseStart(
            $expenseFrom,
            $incomeDay,
        );

        return [
            'debit' => [$expenseFrom, $expenseTo],
            'credit' => [$incomeFrom, $incomeTo],
        ];
    }

    /**
     * Income cycle sharing the expense cycle's start month (or next month if income day is earlier).
     *
     * @return array{0: string, 1: string}
     */
    public static function incomeBoundsAlignedToExpenseStart(string $expenseFrom, int $incomeDay): array
    {
        $tz = (string) config('app.timezone');
        $expenseStart = CarbonImmutable::parse($expenseFrom, $tz)->startOfDay();
        $incomeDay = max(1, min(31, $incomeDay));

        $incomeFrom = self::dateOnMonth($expenseStart->year, $expenseStart->month, $incomeDay);
        if ($incomeFrom->lt($expenseStart)) {
            $next = $expenseStart->addMonthNoOverflow()->startOfMonth();
            $incomeFrom = self::dateOnMonth($next->year, $next->month, $incomeDay);
        }

        $endMonth = $incomeFrom->addMonthNoOverflow()->startOfMonth();
        $incomeTo = self::dateOnMonth($endMonth->year, $endMonth->month, $incomeDay);

        return [
            $incomeFrom->format('Y-m-d'),
            $incomeTo->format('Y-m-d'),
        ];
    }

    /**
     * @return array{0: string, 1: string}|null Null for custom/all/cycles (caller supplies).
     */
    public static function boundsForPreset(string $preset, ?DateTimeInterface $at = null): ?array
    {
        return match ($preset) {
            self::PRESET_CURRENT_MONTH => self::currentMonthBounds($at),
            self::PRESET_LAST_30 => self::lastNDaysBounds(30, $at),
            self::PRESET_LAST_90 => self::lastNDaysBounds(90, $at),
            self::PRESET_ALL => null,
            default => null,
        };
    }

    public static function isCyclePreset(?string $preset): bool
    {
        return in_array($preset, self::CYCLE_PRESETS, true);
    }

    /**
     * Inclusive day count between Y-m-d (0 if inverted / unparseable).
     */
    public static function inclusiveDaySpan(string $from, string $to): int
    {
        try {
            $tz = (string) config('app.timezone');
            $start = CarbonImmutable::parse($from, $tz)->startOfDay();
            $end = CarbonImmutable::parse($to, $tz)->startOfDay();
        } catch (\Throwable) {
            return 0;
        }

        if ($end->lt($start)) {
            return 0;
        }

        return (int) $start->diffInDays($end) + 1;
    }

    /**
     * @return 'day'|'month'
     */
    public static function resolveGroupBy(string $from, string $to): string
    {
        return self::inclusiveDaySpan($from, $to) <= self::GROUP_BY_DAY_MAX_DAYS
            ? 'day'
            : 'month';
    }

    public static function isAllowedPreset(?string $preset): bool
    {
        $preset = strtolower(trim((string) $preset));

        return $preset === '' || in_array($preset, self::PRESETS, true);
    }

    public static function normalizePreset(?string $preset): ?string
    {
        $preset = strtolower(trim((string) $preset));

        if ($preset === '' || ! in_array($preset, self::PRESETS, true)) {
            return null;
        }

        return $preset;
    }

    private static function dateOnMonth(int $year, int $month, int $day): CarbonImmutable
    {
        $tz = (string) config('app.timezone');
        $base = CarbonImmutable::create($year, $month, 1, 0, 0, 0, $tz);
        $clamped = min($day, $base->daysInMonth);

        return $base->day($clamped)->startOfDay();
    }

    private static function now(?DateTimeInterface $at = null): CarbonImmutable
    {
        return CarbonImmutable::instance(
            $at ?? CarbonImmutable::now(config('app.timezone'))
        )->timezone(config('app.timezone'));
    }
}

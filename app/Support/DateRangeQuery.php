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

    /** Inclusive day span at or below this → series group_by=day. */
    public const GROUP_BY_DAY_MAX_DAYS = 45;

    /** @var list<string> */
    public const PRESETS = [
        self::PRESET_CURRENT_MONTH,
        self::PRESET_LAST_30,
        self::PRESET_LAST_90,
        self::PRESET_ALL,
        self::PRESET_CUSTOM,
    ];

    /** Named presets that compute from/to (not custom / all). */
    /** @var list<string> */
    public const COMPUTED_PRESETS = [
        self::PRESET_CURRENT_MONTH,
        self::PRESET_LAST_30,
        self::PRESET_LAST_90,
        self::PRESET_ALL,
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
     * @return array{0: string, 1: string}|null Null for custom/all (caller supplies or omits from/to).
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

    private static function now(?DateTimeInterface $at = null): CarbonImmutable
    {
        return CarbonImmutable::instance(
            $at ?? CarbonImmutable::now(config('app.timezone'))
        )->timezone(config('app.timezone'));
    }
}

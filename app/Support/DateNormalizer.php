<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Date normalization for statement parsers (Etapa C §3.3.2).
 * Invalid dates throw InvalidArgumentException (row-level — parsers catch per line).
 */
final class DateNormalizer
{
    /**
     * Brazilian date dd/mm/yyyy (also accepts d/m/yyyy) → Y-m-d.
     */
    public static function fromBrazilian(string $raw): string
    {
        $raw = trim(str_replace("\xC2\xA0", ' ', $raw));

        if (! preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $raw, $m)) {
            throw new InvalidArgumentException("Invalid Brazilian date [{$raw}].");
        }

        $day = (int) $m[1];
        $month = (int) $m[2];
        $year = (int) $m[3];

        return self::formatValidated($year, $month, $day, $raw);
    }

    /**
     * OFX DTPOSTED: YYYYMMDD or YYYYMMDDHHMMSS[.XXX][timezone] → Y-m-d.
     */
    public static function fromOfx(string $raw): string
    {
        $raw = trim($raw);

        // Strip optional timezone suffix e.g. [-3:GMT] or [0:GMT]
        $raw = preg_replace('/\[.*$/', '', $raw) ?? $raw;
        $raw = trim($raw);

        if (! preg_match('/^(\d{4})(\d{2})(\d{2})(?:\d{6}(?:\.\d+)?)?$/', $raw, $m)) {
            throw new InvalidArgumentException("Invalid OFX date [{$raw}].");
        }

        return self::formatValidated((int) $m[1], (int) $m[2], (int) $m[3], $raw);
    }

    private static function formatValidated(int $year, int $month, int $day, string $original): string
    {
        if (! checkdate($month, $day, $year)) {
            throw new InvalidArgumentException("Invalid calendar date [{$original}].");
        }

        return CarbonImmutable::create($year, $month, $day)->format('Y-m-d');
    }
}

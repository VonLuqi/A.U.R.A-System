<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Monetary parsing / sanitization for statement parsers (Etapa C §3.3.1).
 *
 * Amounts returned by parse* methods are always absolute with 2 decimals ("1234.56").
 * Sign is exposed separately so callers can map credit|debit.
 */
final class Money
{
    /**
     * Parse Brazilian money literals to an absolute 2-decimal string.
     * Examples: "1.234,56" / "-1.234,56" / "R$ 1234,56" / NBSP variants.
     */
    public static function parseBrazilian(string $raw): string
    {
        return self::parseBrazilianSigned($raw)['amount'];
    }

    /**
     * @return array{amount: string, type: 'credit'|'debit', negative: bool}
     */
    public static function parseBrazilianSigned(string $raw): array
    {
        $cleaned = self::stripCurrencyNoise($raw);
        $negative = self::detectAndStripSign($cleaned);

        // Brazilian: thousands "." and decimal ","
        if (str_contains($cleaned, ',')) {
            $cleaned = str_replace('.', '', $cleaned);
            $cleaned = str_replace(',', '.', $cleaned);
        }

        return self::finalize($cleaned, $negative);
    }

    /**
     * Parse OFX / US-style amounts (dot decimal) to absolute 2-decimal string.
     */
    public static function parseOfx(string|float|int $raw): string
    {
        return self::parseOfxSigned($raw)['amount'];
    }

    /**
     * @return array{amount: string, type: 'credit'|'debit', negative: bool}
     */
    public static function parseOfxSigned(string|float|int $raw): array
    {
        if (is_float($raw) || is_int($raw)) {
            $negative = $raw < 0;
            $cleaned = (string) abs($raw);

            return self::finalize($cleaned, $negative);
        }

        $cleaned = self::stripCurrencyNoise((string) $raw);
        $negative = self::detectAndStripSign($cleaned);
        $cleaned = str_replace(',', '', $cleaned);

        return self::finalize($cleaned, $negative);
    }

    public static function assertNonNegative(string $amount): void
    {
        if (! preg_match('/^\d+\.\d{2}$/', $amount)) {
            throw new InvalidArgumentException("amount must be absolute with 2 decimals, got [{$amount}].");
        }
    }

    private static function stripCurrencyNoise(string $raw): string
    {
        $cleaned = str_replace("\xC2\xA0", ' ', $raw); // NBSP
        $cleaned = preg_replace('/\s+/u', '', $cleaned) ?? '';
        $cleaned = str_ireplace(['R$', 'BRL'], '', $cleaned);

        return trim($cleaned);
    }

    private static function detectAndStripSign(string &$cleaned): bool
    {
        $negative = false;

        if (str_starts_with($cleaned, '(') && str_ends_with($cleaned, ')')) {
            $negative = true;
            $cleaned = substr($cleaned, 1, -1);
        }

        if (str_starts_with($cleaned, '+')) {
            $cleaned = substr($cleaned, 1);
        } elseif (str_starts_with($cleaned, '-')) {
            $negative = true;
            $cleaned = substr($cleaned, 1);
        }

        // Trailing minus (some bank exports)
        if (str_ends_with($cleaned, '-')) {
            $negative = true;
            $cleaned = substr($cleaned, 0, -1);
        }

        return $negative;
    }

    /**
     * @return array{amount: string, type: 'credit'|'debit', negative: bool}
     */
    private static function finalize(string $numeric, bool $negative): array
    {
        if ($numeric === '' || ! is_numeric($numeric)) {
            throw new InvalidArgumentException("Invalid money value [{$numeric}].");
        }

        $amount = number_format(abs((float) $numeric), 2, '.', '');
        self::assertNonNegative($amount);

        return [
            'amount' => $amount,
            'type' => $negative ? 'debit' : 'credit',
            'negative' => $negative,
        ];
    }
}

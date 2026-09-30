<?php

namespace App\Support;

/**
 * Extrai título base + índice/total de descrições de fatura (Parcela X/Y ou sufixo N/M).
 *
 * @phpstan-type ParsedInstallment array{title: string, current: int, total: int}
 */
final class InstallmentTitleParser
{
    /**
     * @return ParsedInstallment|null
     */
    public static function parse(?string $description): ?array
    {
        if ($description === null) {
            return null;
        }

        $raw = trim($description);
        if ($raw === '') {
            return null;
        }

        // "… Parcela 4/10" or "… parcela 4 / 10"
        if (preg_match('/^(.*?)(?:\s*[-–—:]?\s*)Parcela\s+(\d+)\s*\/\s*(\d+)\s*$/iu', $raw, $m) === 1) {
            return self::build($m[1], (int) $m[2], (int) $m[3]);
        }

        // Trailing "1/12" (Nubank Magazine Luiza 1/12)
        if (preg_match('/^(.*\S)\s+(\d+)\s*\/\s*(\d+)\s*$/u', $raw, $m) === 1) {
            return self::build($m[1], (int) $m[2], (int) $m[3]);
        }

        return null;
    }

    /**
     * @return ParsedInstallment|null
     */
    private static function build(string $title, int $current, int $total): ?array
    {
        $title = trim($title);
        $title = preg_replace('/\s*[-–—:]\s*$/u', '', $title) ?? $title;
        $title = trim($title);

        if ($title === '' || $current < 1 || $total < 1 || $current > $total) {
            return null;
        }

        return [
            'title' => $title,
            'current' => $current,
            'total' => $total,
        ];
    }
}

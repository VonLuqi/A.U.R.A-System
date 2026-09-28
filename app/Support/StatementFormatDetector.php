<?php

namespace App\Support;

use App\Exceptions\UnsupportedStatementFormatException;
use Illuminate\Http\UploadedFile;

/**
 * Detects statement format for upload (Etapa C §3.7).
 *
 * Priority:
 * 1. File extension (.csv → csv, .ofx/.qfx → ofx)
 * 2. Optional content sniff (OFXHEADER / <OFX>) when extension is missing/unknown
 *
 * MVP source is always `nubank` (optional form field later).
 */
final class StatementFormatDetector
{
    public const DEFAULT_SOURCE = 'nubank';

    /**
     * @return 'csv'|'ofx'
     */
    public static function detect(UploadedFile|string $file): string
    {
        $extension = self::extension($file);
        $fromExtension = self::formatFromExtension($extension);

        if ($fromExtension !== null) {
            return $fromExtension;
        }

        $contents = self::sampleContents($file);
        if (self::looksLikeOfx($contents)) {
            return 'ofx';
        }

        throw new UnsupportedStatementFormatException(
            message: 'Formato de extrato não suportado'
                .($extension !== '' ? ": .{$extension}." : '.'),
            format: $extension !== '' ? $extension : null,
            source: self::DEFAULT_SOURCE,
        );
    }

    /**
     * Attributes ready for statement_imports.format + statement_imports.source.
     *
     * @return array{format: 'csv'|'ofx', source: string}
     */
    public static function detectForImport(UploadedFile|string $file, ?string $source = null): array
    {
        return [
            'format' => self::detect($file),
            'source' => self::normalizeSource($source),
        ];
    }

    public static function normalizeSource(?string $source): string
    {
        $source = strtolower(trim((string) $source));

        return $source !== '' ? $source : self::DEFAULT_SOURCE;
    }

    /**
     * @return 'csv'|'ofx'|null
     */
    public static function formatFromExtension(string $extension): ?string
    {
        return match (strtolower(trim($extension))) {
            'csv' => 'csv',
            'ofx', 'qfx' => 'ofx',
            default => null,
        };
    }

    public static function looksLikeOfx(string $contents): bool
    {
        $head = substr($contents, 0, 4096);

        if (stripos($head, 'OFXHEADER') !== false) {
            return true;
        }

        if (stripos($head, '<OFX>') !== false || stripos($head, '<OFX ') !== false) {
            return true;
        }

        return false;
    }

    private static function extension(UploadedFile|string $file): string
    {
        if ($file instanceof UploadedFile) {
            return strtolower($file->getClientOriginalExtension());
        }

        return strtolower(pathinfo($file, PATHINFO_EXTENSION));
    }

    private static function sampleContents(UploadedFile|string $file): string
    {
        if ($file instanceof UploadedFile) {
            $path = $file->getRealPath() ?: $file->getPathname();
            if (! is_string($path) || $path === '' || ! is_readable($path)) {
                return '';
            }

            $chunk = file_get_contents($path, false, null, 0, 4096);

            return is_string($chunk) ? $chunk : '';
        }

        if (! is_readable($file)) {
            return '';
        }

        $chunk = file_get_contents($file, false, null, 0, 4096);

        return is_string($chunk) ? $chunk : '';
    }
}

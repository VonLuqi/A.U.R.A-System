<?php

namespace App\Support;

use App\Enums\DetectedFormat;
use App\Enums\StatementFormat;
use App\Enums\StatementSource;
use App\Exceptions\UnsupportedStatementFormatException;
use Illuminate\Http\UploadedFile;

/**
 * Detects statement format for upload (Etapa C §3.7 / PLAN_EXPANSAO §5.1).
 *
 * Priority:
 * 1. Manual override: statement_kind=checking|credit_card
 * 2. source=nubank_credit + CSV extension → csv_credit_card
 * 3. Extension (.ofx/.qfx → ofx; .csv → header sniff)
 * 4. Content sniff (OFXHEADER / <OFX>)
 */
final class StatementFormatDetector
{
    public const DEFAULT_SOURCE = StatementSource::Nubank->value;

    public const KIND_CHECKING = 'checking';

    public const KIND_CREDIT_CARD = 'credit_card';

    /** @var list<string> */
    public const ALLOWED_SOURCES = [
        StatementSource::Nubank->value,
        StatementSource::NubankCredit->value,
        StatementSource::Other->value,
    ];

    /** @var list<string> */
    public const ALLOWED_FORMATS = [
        StatementFormat::Csv->value,
        StatementFormat::Ofx->value,
        StatementFormat::CsvCreditCard->value,
    ];

    /** @var list<string> */
    public const ALLOWED_KINDS = [
        self::KIND_CHECKING,
        self::KIND_CREDIT_CARD,
    ];

    /**
     * @return 'csv'|'csv_credit_card'|'ofx'
     */
    public static function detect(
        UploadedFile|string $file,
        ?string $source = null,
        ?string $statementKind = null,
    ): string {
        return self::detectEnum($file, $source, $statementKind)->value;
    }

    public static function detectEnum(
        UploadedFile|string $file,
        ?string $source = null,
        ?string $statementKind = null,
    ): DetectedFormat {
        $source = self::normalizeSource($source);
        $kind = self::normalizeKind($statementKind);
        $extension = self::extension($file);
        $fromExtension = self::formatFromExtension($extension);

        if ($fromExtension === StatementFormat::Ofx->value) {
            return DetectedFormat::Ofx;
        }

        if ($fromExtension === StatementFormat::Csv->value) {
            return self::resolveCsvFormat($file, $source, $kind);
        }

        $contents = self::sampleContents($file);
        if (self::looksLikeOfx($contents)) {
            return DetectedFormat::Ofx;
        }

        // No extension but explicit CSV kind / credit source — still treat as CSV.
        if ($kind !== null || $source === StatementSource::NubankCredit->value) {
            return self::resolveCsvFormat($file, $source, $kind);
        }

        throw new UnsupportedStatementFormatException(
            message: 'Formato de extrato não suportado'
                .($extension !== '' ? ": .{$extension}." : '.'),
            format: $extension !== '' ? $extension : null,
            source: $source !== '' ? $source : self::DEFAULT_SOURCE,
        );
    }

    private static function resolveCsvFormat(
        UploadedFile|string $file,
        string $source,
        ?string $kind,
    ): DetectedFormat {
        if ($kind === self::KIND_CREDIT_CARD) {
            return DetectedFormat::CsvCreditCard;
        }

        if ($kind === self::KIND_CHECKING) {
            return DetectedFormat::CsvChecking;
        }

        if ($source === StatementSource::NubankCredit->value) {
            return DetectedFormat::CsvCreditCard;
        }

        return self::sniffCsvKind($file);
    }

    /**
     * Attributes ready for statement_imports.format + statement_imports.source.
     *
     * @return array{format: string, source: string}
     */
    public static function detectForImport(
        UploadedFile|string $file,
        ?string $source = null,
        ?string $statementKind = null,
    ): array {
        $source = self::normalizeSource($source);
        $format = self::detect($file, $source, $statementKind);

        if ($format === StatementFormat::CsvCreditCard->value
            && $source === self::DEFAULT_SOURCE) {
            $source = StatementSource::NubankCredit->value;
        }

        return [
            'format' => $format,
            'source' => $source,
        ];
    }

    public static function normalizeSource(?string $source): string
    {
        $source = strtolower(trim((string) $source));

        if ($source === '') {
            return self::DEFAULT_SOURCE;
        }

        return $source;
    }

    public static function normalizeKind(?string $kind): ?string
    {
        $kind = strtolower(trim((string) $kind));

        if ($kind === '') {
            return null;
        }

        return in_array($kind, self::ALLOWED_KINDS, true) ? $kind : null;
    }

    public static function isAllowedSource(?string $source): bool
    {
        $source = strtolower(trim((string) $source));

        return $source === '' || in_array($source, self::ALLOWED_SOURCES, true);
    }

    public static function isAllowedKind(?string $kind): bool
    {
        $kind = strtolower(trim((string) $kind));

        return $kind === '' || in_array($kind, self::ALLOWED_KINDS, true);
    }

    /**
     * @return 'csv'|'ofx'|null
     */
    public static function formatFromExtension(string $extension): ?string
    {
        return match (strtolower(trim($extension))) {
            'csv' => StatementFormat::Csv->value,
            'ofx', 'qfx' => StatementFormat::Ofx->value,
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

    /**
     * Sniff CSV header line: credit-card (date/title/amount) vs checking (data/valor/descricao).
     */
    public static function sniffCsvKind(UploadedFile|string $file): DetectedFormat
    {
        $headers = self::normalizedHeaderTokens($file);

        if ($headers === []) {
            return DetectedFormat::CsvChecking;
        }

        $hasTitle = in_array('title', $headers, true) || in_array('titulo', $headers, true);
        $hasDateEn = in_array('date', $headers, true);
        $hasAmountEn = in_array('amount', $headers, true);
        $hasData = in_array('data', $headers, true);
        $hasValor = in_array('valor', $headers, true);
        $hasDescricao = in_array('descricao', $headers, true);
        $hasIdentificador = in_array('identificador', $headers, true);

        // Modern Nubank CC export: date, title, amount [, category]
        if ($hasDateEn && $hasTitle && $hasAmountEn) {
            return DetectedFormat::CsvCreditCard;
        }

        // Checking export typically has Identificador; CC English export does not.
        if ($hasData && $hasValor && $hasDescricao && $hasIdentificador) {
            return DetectedFormat::CsvChecking;
        }

        // title + amount without checking identificador → credit card
        if ($hasTitle && ($hasAmountEn || $hasValor) && ! $hasIdentificador) {
            return DetectedFormat::CsvCreditCard;
        }

        return DetectedFormat::CsvChecking;
    }

    /**
     * @return list<string>
     */
    public static function normalizedHeaderTokens(UploadedFile|string $file): array
    {
        $contents = self::sampleContents($file, 2048);
        if ($contents === '') {
            return [];
        }

        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr($contents, 3);
        }

        $firstLine = strtok($contents, "\r\n") ?: '';
        if ($firstLine === '') {
            return [];
        }

        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        $cells = str_getcsv($firstLine, $delimiter, '"', '\\');

        $tokens = [];
        foreach ($cells as $cell) {
            $normalized = self::normalizeHeaderToken((string) $cell);
            if ($normalized !== '') {
                $tokens[] = $normalized;
            }
        }

        return $tokens;
    }

    public static function normalizeHeaderToken(string $name): string
    {
        $name = trim(str_replace("\xC2\xA0", ' ', $name));
        $name = mb_strtolower($name, 'UTF-8');
        $name = strtr($name, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);

        return preg_replace('/\s+/u', '', $name) ?? '';
    }

    private static function extension(UploadedFile|string $file): string
    {
        if ($file instanceof UploadedFile) {
            return strtolower($file->getClientOriginalExtension());
        }

        return strtolower(pathinfo($file, PATHINFO_EXTENSION));
    }

    private static function sampleContents(UploadedFile|string $file, int $bytes = 4096): string
    {
        if ($file instanceof UploadedFile) {
            $path = $file->getRealPath() ?: $file->getPathname();
            if (! is_string($path) || $path === '' || ! is_readable($path)) {
                return '';
            }

            $chunk = file_get_contents($path, false, null, 0, $bytes);

            return is_string($chunk) ? $chunk : '';
        }

        if (! is_readable($file)) {
            return '';
        }

        $chunk = file_get_contents($file, false, null, 0, $bytes);

        return is_string($chunk) ? $chunk : '';
    }
}

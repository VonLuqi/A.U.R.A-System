<?php

namespace App\Parsers;

use App\DTOs\ParsedTransaction;
use App\DTOs\ParseResult;
use App\Enums\StatementFormat;
use App\Enums\StatementSource;
use App\Exceptions\InvalidStatementException;
use App\Parsers\Contracts\StatementParserInterface;
use App\Support\DateNormalizer;
use App\Support\Money;
use App\Support\StatementFormatDetector;
use InvalidArgumentException;
use SplFileInfo;

/**
 * Nubank credit-card invoice CSV parser (PLAN_EXPANSAO §5.1).
 *
 * Expected headers (case-insensitive):
 * - Modern export: date, title, amount [, category]
 * - Also accepts: data, titulo|descricao, valor
 *
 * Amount sign convention (card invoice):
 * - Positive → debit (purchase / charge)
 * - Negative → credit (refund / estorno)
 *
 * Skip patterns (Pagamento recebido, totals, …) come from config aura.statements.
 */
final class NubankCreditCardCsvParser implements StatementParserInterface
{
    public function supports(string $format, string $source): bool
    {
        return $format === StatementFormat::CsvCreditCard->value
            && in_array($source, [
                StatementSource::NubankCredit->value,
                StatementSource::Nubank->value,
                StatementSource::Other->value,
            ], true);
    }

    public function parse(SplFileInfo|string $file): ParseResult
    {
        $path = $file instanceof SplFileInfo ? $file->getPathname() : $file;

        if (! is_string($path) || $path === '' || ! is_readable($path)) {
            throw new InvalidStatementException('Arquivo de extrato ilegível ou inexistente.');
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new InvalidStatementException('Não foi possível ler o arquivo de extrato.');
        }

        $contents = $this->normalizeEncoding($contents);
        $contents = $this->stripBom($contents);

        $delimiter = $this->detectDelimiter($contents);
        $rows = $this->readCsvRows($contents, $delimiter);

        if ($rows === []) {
            throw new InvalidStatementException('Extrato CSV de cartão vazio.');
        }

        $headers = $this->mapHeaders(array_shift($rows));

        $transactions = [];
        $rowErrors = [];
        $rowsTotal = 0;
        $lineNumber = 1;

        foreach ($rows as $row) {
            $lineNumber++;

            if ($this->isEmptyRow($row)) {
                continue;
            }

            $rowsTotal++;
            $normalized = $this->rowToNormalized($row, $headers);
            $rawPayload = $this->rowToOriginal($row, $headers);
            $rawPayload['card_source'] = StatementSource::NubankCredit->value;

            try {
                $tx = $this->mapRow($normalized, $rawPayload);
                if ($tx === null) {
                    $rowErrors[] = ['line' => $lineNumber, 'message' => 'Linha ignorada (totalizador/pagamento/zero).'];

                    continue;
                }
                $transactions[] = $tx;
            } catch (InvalidArgumentException $e) {
                $rowErrors[] = ['line' => $lineNumber, 'message' => $e->getMessage()];
            }
        }

        return new ParseResult(
            transactions: $transactions,
            rowsTotal: $rowsTotal,
            rowErrors: $rowErrors,
            format: StatementFormat::CsvCreditCard->value,
            source: StatementSource::NubankCredit->value,
        );
    }

    private function normalizeEncoding(string $contents): string
    {
        if (mb_check_encoding($contents, 'UTF-8')) {
            return $contents;
        }

        $converted = @mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');

        return is_string($converted) ? $converted : $contents;
    }

    private function stripBom(string $contents): string
    {
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            return substr($contents, 3);
        }

        return $contents;
    }

    private function detectDelimiter(string $contents): string
    {
        $firstLine = strtok($contents, "\r\n") ?: '';
        $commas = substr_count($firstLine, ',');
        $semicolons = substr_count($firstLine, ';');

        return $semicolons > $commas ? ';' : ',';
    }

    /**
     * @return list<list<string|null>>
     */
    private function readCsvRows(string $contents, string $delimiter): array
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new InvalidStatementException('Falha ao preparar buffer CSV.');
        }

        fwrite($handle, $contents);
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  list<string|null>  $headerRow
     * @return array{indexes: array<string, int>, labels: array<string, string>}
     */
    private function mapHeaders(array $headerRow): array
    {
        $indexes = [];
        $labels = [];

        foreach ($headerRow as $index => $name) {
            $original = trim(str_replace("\xC2\xA0", ' ', (string) $name));
            $normalized = StatementFormatDetector::normalizeHeaderToken($original);
            if ($normalized === '') {
                continue;
            }

            $canonical = match ($normalized) {
                'date', 'data' => 'date',
                'amount', 'valor' => 'amount',
                'title', 'titulo' => 'title',
                'descricao', 'description' => 'descricao',
                'category', 'categoria' => 'category',
                default => $normalized,
            };

            $indexes[$canonical] = $index;
            $labels[$canonical] = $original;
        }

        if (isset($indexes['title'])) {
            $indexes['description'] = $indexes['title'];
            $labels['description'] = $labels['title'];
        } elseif (isset($indexes['descricao'])) {
            $indexes['description'] = $indexes['descricao'];
            $labels['description'] = $labels['descricao'];
        }

        if (! isset($indexes['date']) || ! isset($indexes['amount']) || ! isset($indexes['description'])) {
            throw new InvalidStatementException(
                'Cabeçalho CSV de cartão inválido: colunas obrigatórias ausentes (date/title/amount ou Data/Descrição/Valor).'
            );
        }

        return ['indexes' => $indexes, 'labels' => $labels];
    }

    /**
     * @param  list<string|null>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string|null>  $row
     * @param  array{indexes: array<string, int>, labels: array<string, string>}  $headers
     * @return array<string, string>
     */
    private function rowToNormalized(array $row, array $headers): array
    {
        $assoc = [];
        foreach ($headers['indexes'] as $normalized => $index) {
            $assoc[$normalized] = isset($row[$index]) ? (string) $row[$index] : '';
        }

        return $assoc;
    }

    /**
     * @param  list<string|null>  $row
     * @param  array{indexes: array<string, int>, labels: array<string, string>}  $headers
     * @return array<string, string>
     */
    private function rowToOriginal(array $row, array $headers): array
    {
        $assoc = [];
        foreach ($headers['indexes'] as $normalized => $index) {
            $label = $headers['labels'][$normalized];
            $assoc[$label] = isset($row[$index]) ? (string) $row[$index] : '';
        }

        return $assoc;
    }

    /**
     * @param  array<string, string>  $normalized
     * @param  array<string, mixed>  $rawPayload
     */
    private function mapRow(array $normalized, array $rawPayload): ?ParsedTransaction
    {
        $date = trim($normalized['date'] ?? '');
        $amountRaw = trim($normalized['amount'] ?? '');
        $description = trim($normalized['description'] ?? '');

        if ($date === '' || $amountRaw === '' || $description === '') {
            throw new InvalidArgumentException('Linha incompleta: colunas obrigatórias ausentes.');
        }

        if ($this->shouldSkipDescription($description)) {
            return null;
        }

        $occurredOn = DateNormalizer::fromAny($date);
        $signed = $this->parseAmount($amountRaw);

        if ($signed['amount'] === '0.00') {
            return null;
        }

        // Card invoice: positive = purchase (debit); negative = refund (credit).
        $type = $signed['negative'] ? 'credit' : 'debit';

        if (isset($normalized['category']) && trim($normalized['category']) !== '') {
            $rawPayload['nubank_category'] = trim($normalized['category']);
        }

        return new ParsedTransaction(
            occurredOn: $occurredOn,
            description: $description,
            amount: $signed['amount'],
            type: $type,
            externalId: null,
            rawPayload: $rawPayload,
        );
    }

    /**
     * @return array{amount: string, type: 'credit'|'debit', negative: bool}
     */
    private function parseAmount(string $raw): array
    {
        // Prefer Brazilian parser when comma decimal is present; else US/ISO style.
        if (str_contains($raw, ',')) {
            return Money::parseBrazilianSigned($raw);
        }

        return Money::parseOfxSigned($raw);
    }

    private function shouldSkipDescription(string $description): bool
    {
        /** @var list<string> $patterns */
        $patterns = config('aura.statements.credit_card_skip_patterns', []);

        foreach ($patterns as $pattern) {
            if (@preg_match($pattern, $description) === 1) {
                return true;
            }
        }

        return false;
    }
}

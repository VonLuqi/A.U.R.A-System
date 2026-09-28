<?php

namespace App\Parsers;

use App\DTOs\ParsedTransaction;
use App\DTOs\ParseResult;
use App\Exceptions\InvalidStatementException;
use App\Parsers\Contracts\StatementParserInterface;
use App\Support\DateNormalizer;
use App\Support\Money;
use InvalidArgumentException;
use SplFileInfo;

/**
 * Nubank checking-account CSV export parser (Etapa C §3.4).
 *
 * Expected headers (case-insensitive): Data, Valor, Descrição [, Identificador]
 * Reads via native fgetcsv (no League\Csv dependency in MVP).
 */
final class NubankCsvParser implements StatementParserInterface
{
    private const REQUIRED_HEADERS = ['data', 'valor', 'descricao'];

    public function supports(string $format, string $source): bool
    {
        return $format === 'csv' && $source === 'nubank';
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
            throw new InvalidStatementException('Extrato CSV vazio.');
        }

        $headers = $this->mapHeaders(array_shift($rows));

        $transactions = [];
        $rowErrors = [];
        $rowsTotal = 0;
        $lineNumber = 1; // header was line 1

        foreach ($rows as $row) {
            $lineNumber++;

            if ($this->isEmptyRow($row)) {
                continue;
            }

            $rowsTotal++;
            $normalized = $this->rowToNormalized($row, $headers);
            $rawPayload = $this->rowToOriginal($row, $headers);

            try {
                $tx = $this->mapRow($normalized, $rawPayload);
                if ($tx === null) {
                    $rowErrors[] = ['line' => $lineNumber, 'message' => 'Valor zero ignorado.'];

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
            format: 'csv',
            source: 'nubank',
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
            $normalized = $this->normalizeHeader($original);
            if ($normalized === '') {
                continue;
            }
            $indexes[$normalized] = $index;
            $labels[$normalized] = $original;
        }

        foreach (self::REQUIRED_HEADERS as $required) {
            if (! array_key_exists($required, $indexes)) {
                throw new InvalidStatementException(
                    'Cabeçalho CSV inválido: coluna obrigatória ausente (Data, Valor, Descrição).'
                );
            }
        }

        return ['indexes' => $indexes, 'labels' => $labels];
    }

    private function normalizeHeader(string $name): string
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
     * Associative row keyed by original CSV header labels (for rawPayload).
     *
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
     * @param  array<string, string>  $rawPayload
     */
    private function mapRow(array $normalized, array $rawPayload): ?ParsedTransaction
    {
        $data = trim($normalized['data'] ?? '');
        $valor = trim($normalized['valor'] ?? '');
        $description = trim($normalized['descricao'] ?? '');

        if ($data === '' || $valor === '' || $description === '') {
            throw new InvalidArgumentException('Linha incompleta: colunas obrigatórias ausentes.');
        }

        $occurredOn = DateNormalizer::fromBrazilian($data);
        $signed = Money::parseBrazilianSigned($valor);

        if ($signed['amount'] === '0.00') {
            return null;
        }

        $externalId = trim($normalized['identificador'] ?? '');
        $externalId = $externalId !== '' ? $externalId : null;

        return new ParsedTransaction(
            occurredOn: $occurredOn,
            description: $description,
            amount: $signed['amount'],
            type: $signed['type'],
            externalId: $externalId,
            rawPayload: $rawPayload,
        );
    }
}

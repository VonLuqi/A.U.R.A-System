<?php

namespace App\DTOs;

use App\Enums\StatementFormat;
use InvalidArgumentException;

/**
 * Result of parsing a statement file (Etapa C §3.2.2).
 *
 * Fatal parse errors must throw InvalidStatementException — never return an empty silent result
 * when the file itself is unreadable / structurally invalid.
 *
 * @phpstan-type RowError array{line: int, message: string}
 */
final readonly class ParseResult
{
    /**
     * @param  list<ParsedTransaction>  $transactions
     * @param  list<RowError>  $rowErrors
     */
    public function __construct(
        public array $transactions,
        public int $rowsTotal,
        public array $rowErrors,
        public string $format,
        public string $source,
    ) {
        if (! in_array($this->format, StatementFormat::values(), true)) {
            throw new InvalidArgumentException(
                'format must be '.implode('|', StatementFormat::values()).", got [{$this->format}]."
            );
        }

        if ($this->source === '') {
            throw new InvalidArgumentException('source must not be empty.');
        }

        if ($this->rowsTotal < 0) {
            throw new InvalidArgumentException('rowsTotal must be >= 0.');
        }

        if ($this->rowsTotal < count($this->transactions)) {
            throw new InvalidArgumentException('rowsTotal cannot be less than transactions count.');
        }

        foreach ($this->transactions as $index => $transaction) {
            if (! $transaction instanceof ParsedTransaction) {
                throw new InvalidArgumentException("transactions[{$index}] must be ParsedTransaction.");
            }
        }

        foreach ($this->rowErrors as $index => $error) {
            if (! is_array($error) || ! isset($error['line'], $error['message'])) {
                throw new InvalidArgumentException("rowErrors[{$index}] must have line and message keys.");
            }
        }
    }

    public function hasRowErrors(): bool
    {
        return $this->rowErrors !== [];
    }

    /**
     * @return list<array{line: int, message: string}>
     */
    public function truncatedRowErrors(int $max = 20): array
    {
        return array_slice($this->rowErrors, 0, max(0, $max));
    }
}

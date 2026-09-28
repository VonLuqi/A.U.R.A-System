<?php

namespace App\DTOs;

use InvalidArgumentException;

/**
 * Normalized transaction line from a statement parser (Etapa C §3.2.1).
 *
 * unique_hash is intentionally omitted — StatementUploadService calls TransactionHasher.
 */
final readonly class ParsedTransaction
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public string $occurredOn,
        public string $description,
        public string $amount,
        public string $type,
        public ?string $externalId,
        public array $rawPayload,
    ) {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->occurredOn)) {
            throw new InvalidArgumentException("occurredOn must be Y-m-d, got [{$this->occurredOn}].");
        }

        if (! preg_match('/^\d+\.\d{2}$/', $this->amount)) {
            throw new InvalidArgumentException("amount must be absolute with 2 decimals, got [{$this->amount}].");
        }

        if (! in_array($this->type, ['credit', 'debit'], true)) {
            throw new InvalidArgumentException("type must be credit|debit, got [{$this->type}].");
        }

        if ($this->description === '') {
            throw new InvalidArgumentException('description must not be empty.');
        }
    }
}

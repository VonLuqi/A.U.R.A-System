<?php

namespace App\Support;

final class TransactionHasher
{
    /**
     * Canonical unique hash for cross-import idempotency (SHA-256 hex, 64 chars).
     *
     * Format: occurred_on|amount|type|description_normalized|external_id|source
     * Does NOT include statement_import_id.
     *
     * Import policy (Etapa C / MVP): on unique_hash conflict, SKIP the row and
     * increment statement_imports.rows_skipped — never silently upsert/update.
     */
    public static function make(
        string $occurredOn,
        string|float|int $amount,
        string $type,
        string $description,
        ?string $externalId = null,
        string $source = 'nubank',
    ): string {
        $description = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $description) ?? ''));
        $amount = number_format((float) $amount, 2, '.', '');
        $externalId = $externalId ?? '';

        $canonical = implode('|', [
            $occurredOn,
            $amount,
            $type,
            $description,
            $externalId,
            $source,
        ]);

        return hash('sha256', $canonical);
    }
}

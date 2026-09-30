<?php

namespace App\Support;

/**
 * Canonical unique hash for cross-import idempotency (SHA-256 hex, 64 chars).
 *
 * Format: occurred_on|amount|type|description_normalized|external_id|source
 *
 * Does NOT include `user_id` or `statement_import_id` in the payload. Multi-tenant
 * isolation is enforced by the DB unique index `(user_id, unique_hash)`.
 *
 * Import policy: on `(user_id, unique_hash)` conflict, do not insert a second
 * row. Reimport may patch safe fields (credit_card_id, alias description/
 * category) and count those as rows_updated; untouched duplicates still
 * increment statement_imports.rows_skipped.
 */
final class TransactionHasher
{
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

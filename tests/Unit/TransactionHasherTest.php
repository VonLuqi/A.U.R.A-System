<?php

namespace Tests\Unit;

use App\Support\TransactionHasher;
use PHPUnit\Framework\TestCase;

/**
 * PLAN_EXPANSAO §9.1 — TransactionHasher contracts.
 */
class TransactionHasherTest extends TestCase
{
    public function test_same_input_produces_same_hash(): void
    {
        $a = TransactionHasher::make('2026-01-15', '12.50', 'debit', 'Uber Trip');
        $b = TransactionHasher::make('2026-01-15', '12.50', 'debit', 'Uber Trip');

        $this->assertSame($a, $b);
        $this->assertSame(64, strlen($a));
    }

    public function test_description_normalization_is_stable(): void
    {
        $normalized = TransactionHasher::make('2026-01-15', '12.50', 'debit', 'uber trip');
        $withNoise = TransactionHasher::make('2026-01-15', 12.5, 'debit', '  Uber   Trip  ');

        $this->assertSame($normalized, $withNoise);
    }

    public function test_amount_change_produces_different_hash(): void
    {
        $a = TransactionHasher::make('2026-01-15', '12.50', 'debit', 'uber trip');
        $b = TransactionHasher::make('2026-01-15', '12.51', 'debit', 'uber trip');

        $this->assertNotSame($a, $b);
    }

    public function test_null_external_id_equals_empty_string(): void
    {
        $a = TransactionHasher::make('2026-01-15', '12.50', 'debit', 'uber trip', null);
        $b = TransactionHasher::make('2026-01-15', '12.50', 'debit', 'uber trip', '');

        $this->assertSame($a, $b);
    }

    public function test_manual_source_marker_does_not_collide_with_import_for_same_user_payload(): void
    {
        $occurredOn = '2026-09-15';
        $amount = '42.50';
        $type = 'debit';
        $description = 'Farmácia Extra';
        $userId = 7;

        $importHash = TransactionHasher::make(
            $occurredOn,
            $amount,
            $type,
            $description,
            null,
            'nubank',
        );

        $manualHash = TransactionHasher::make(
            $occurredOn,
            $amount,
            $type,
            $description,
            null,
            'manual:'.$userId,
        );

        $this->assertNotSame(
            $importHash,
            $manualHash,
            'Manual rows use source=manual:{userId} so they do not collide with import hashes.'
        );
    }

    public function test_manual_source_marker_differs_per_user(): void
    {
        $hashA = TransactionHasher::make('2026-09-15', '10.00', 'credit', 'Salário', null, 'manual:1');
        $hashB = TransactionHasher::make('2026-09-15', '10.00', 'credit', 'Salário', null, 'manual:2');

        $this->assertNotSame($hashA, $hashB);
    }

    public function test_identical_import_payload_yields_same_hash_across_users(): void
    {
        // Isolation is DB unique (user_id, unique_hash) — hash itself may repeat across tenants.
        $hashA = TransactionHasher::make('2026-01-15', '10.00', 'debit', 'Same row', 'ext-1', 'nubank');
        $hashB = TransactionHasher::make('2026-01-15', '10.00', 'debit', 'Same row', 'ext-1', 'nubank');

        $this->assertSame($hashA, $hashB);
    }
}

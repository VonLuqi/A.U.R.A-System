<?php

namespace Tests\Unit;

use App\Support\TransactionHasher;
use PHPUnit\Framework\TestCase;

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
}

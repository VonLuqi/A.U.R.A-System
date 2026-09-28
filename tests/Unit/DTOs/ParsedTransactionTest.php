<?php

namespace Tests\Unit\DTOs;

use App\DTOs\ParsedTransaction;
use InvalidArgumentException;
use Tests\TestCase;

class ParsedTransactionTest extends TestCase
{
    public function test_it_accepts_a_valid_normalized_line(): void
    {
        $tx = new ParsedTransaction(
            occurredOn: '2026-09-27',
            description: 'Supermercado Extra',
            amount: '89.90',
            type: 'debit',
            externalId: null,
            rawPayload: ['Data' => '27/09/2026', 'Valor' => '-89,90'],
        );

        $this->assertSame('2026-09-27', $tx->occurredOn);
        $this->assertSame('89.90', $tx->amount);
        $this->assertSame('debit', $tx->type);
        $this->assertNull($tx->externalId);
        $this->assertArrayHasKey('Valor', $tx->rawPayload);
        $this->assertObjectNotHasProperty('uniqueHash', $tx);
    }

    public function test_it_rejects_invalid_occurred_on(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ParsedTransaction(
            occurredOn: '27/09/2026',
            description: 'X',
            amount: '1.00',
            type: 'credit',
            externalId: null,
            rawPayload: [],
        );
    }

    public function test_it_rejects_non_absolute_amount_format(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ParsedTransaction(
            occurredOn: '2026-09-27',
            description: 'X',
            amount: '-1.00',
            type: 'debit',
            externalId: null,
            rawPayload: [],
        );
    }

    public function test_it_rejects_invalid_type(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ParsedTransaction(
            occurredOn: '2026-09-27',
            description: 'X',
            amount: '1.00',
            type: 'transfer',
            externalId: null,
            rawPayload: [],
        );
    }
}

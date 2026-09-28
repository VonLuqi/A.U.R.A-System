<?php

namespace Tests\Unit\DTOs;

use App\DTOs\ParsedTransaction;
use App\DTOs\ParseResult;
use App\Exceptions\InvalidStatementException;
use InvalidArgumentException;
use Tests\TestCase;

class ParseResultTest extends TestCase
{
    public function test_it_accepts_a_valid_result_with_row_errors(): void
    {
        $tx = new ParsedTransaction(
            occurredOn: '2026-09-01',
            description: 'Pix',
            amount: '10.00',
            type: 'credit',
            externalId: null,
            rawPayload: [],
        );

        $result = new ParseResult(
            transactions: [$tx],
            rowsTotal: 2,
            rowErrors: [
                ['line' => 3, 'message' => 'Data inválida'],
            ],
            format: 'csv',
            source: 'nubank',
        );

        $this->assertSame(2, $result->rowsTotal);
        $this->assertTrue($result->hasRowErrors());
        $this->assertCount(1, $result->truncatedRowErrors());
    }

    public function test_it_rejects_invalid_format(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ParseResult(
            transactions: [],
            rowsTotal: 0,
            rowErrors: [],
            format: 'pdf',
            source: 'nubank',
        );
    }

    public function test_it_rejects_rows_total_below_transaction_count(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ParseResult(
            transactions: [
                new ParsedTransaction(
                    occurredOn: '2026-09-01',
                    description: 'X',
                    amount: '1.00',
                    type: 'debit',
                    externalId: null,
                    rawPayload: [],
                ),
            ],
            rowsTotal: 0,
            rowErrors: [],
            format: 'csv',
            source: 'nubank',
        );
    }

    public function test_invalid_statement_exception_sanitizes_paths(): void
    {
        $e = new InvalidStatementException(
            'Failed reading /home4/luca9682/aura/storage/app/private/statements/x.csv'
        );

        $this->assertStringNotContainsString('/home4/', $e->getMessage());
        $this->assertStringContainsString('[path]', $e->getMessage());
        $this->assertSame('invalid_statement', $e->errorCode);
    }
}

<?php

namespace Tests\Unit\Parsers;

use App\DTOs\ParsedTransaction;
use App\DTOs\ParseResult;
use App\Parsers\Contracts\StatementParserInterface;
use SplFileInfo;
use Tests\TestCase;

class StatementParserInterfaceTest extends TestCase
{
    public function test_contract_methods_are_implementable(): void
    {
        $parser = new class implements StatementParserInterface
        {
            public function supports(string $format, string $source): bool
            {
                return $format === 'csv' && $source === 'nubank';
            }

            public function parse(SplFileInfo|string $file): ParseResult
            {
                return new ParseResult(
                    transactions: [
                        new ParsedTransaction(
                            occurredOn: '2026-09-01',
                            description: 'Test',
                            amount: '10.00',
                            type: 'debit',
                            externalId: null,
                            rawPayload: ['line' => 1],
                        ),
                    ],
                    rowsTotal: 1,
                    rowErrors: [],
                    format: 'csv',
                    source: 'nubank',
                );
            }
        };

        $this->assertTrue($parser->supports('csv', 'nubank'));
        $this->assertFalse($parser->supports('ofx', 'nubank'));

        $result = $parser->parse(__FILE__);

        $this->assertSame('csv', $result->format);
        $this->assertSame('nubank', $result->source);
        $this->assertCount(1, $result->transactions);
        $this->assertSame('10.00', $result->transactions[0]->amount);
        $this->assertObjectNotHasProperty('uniqueHash', $result->transactions[0]);
    }
}

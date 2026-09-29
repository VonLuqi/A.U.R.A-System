<?php

namespace Tests\Unit\Parsers;

use App\DTOs\ParsedTransaction;
use App\Exceptions\InvalidStatementException;
use App\Parsers\Contracts\StatementParserInterface;
use App\Parsers\OfxParser;
use SplFileInfo;
use Tests\TestCase;

class OfxParserTest extends TestCase
{
    private function fixture(string $name): string
    {
        return base_path('tests/Fixtures/statements/ofx/'.$name);
    }

    public function test_implements_interface_and_supports_ofx_qfx_nubank(): void
    {
        $parser = new OfxParser;

        $this->assertInstanceOf(StatementParserInterface::class, $parser);
        $this->assertTrue($parser->supports('ofx', 'nubank'));
        $this->assertTrue($parser->supports('qfx', 'nubank'));
        $this->assertTrue($parser->supports('ofx', 'other'));
        $this->assertFalse($parser->supports('csv', 'nubank'));
        $this->assertFalse($parser->supports('ofx', 'nubank_credit'));
    }

    public function test_parses_nubank_ofx_fixture_into_typed_transactions(): void
    {
        $result = (new OfxParser)->parse($this->fixture('sample_nubank.ofx'));

        $this->assertSame('ofx', $result->format);
        $this->assertSame('nubank', $result->source);
        $this->assertSame(4, $result->rowsTotal);
        $this->assertCount(3, $result->transactions); // zero skipped
        $this->assertTrue($result->hasRowErrors());
        $this->assertContains('Valor zero ignorado.', array_column($result->rowErrors, 'message'));

        foreach ($result->transactions as $tx) {
            $this->assertInstanceOf(ParsedTransaction::class, $tx);
        }

        $debit = $result->transactions[0];
        $this->assertSame('2026-09-01', $debit->occurredOn);
        $this->assertSame('89.90', $debit->amount);
        $this->assertSame('debit', $debit->type);
        $this->assertSame('nubank-fitid-001', $debit->externalId);
        $this->assertSame('Compra supermercado', $debit->description); // MEMO preferred
        $this->assertSame('Compra supermercado', $debit->rawPayload['MEMO']);
        $this->assertSame('SUPERMERCADO EXTRA', $debit->rawPayload['NAME']);

        $credit = $result->transactions[1];
        $this->assertSame('1250.00', $credit->amount);
        $this->assertSame('credit', $credit->type);

        $nameFallback = $result->transactions[2];
        $this->assertSame('PADARIA CAFE', $nameFallback->description); // no MEMO → NAME
        $this->assertSame('15.50', $nameFallback->amount);
        $this->assertSame('debit', $nameFallback->type);
    }

    public function test_fitid_maps_to_external_id(): void
    {
        $result = (new OfxParser)->parse(new SplFileInfo($this->fixture('sample_nubank.ofx')));

        $ids = array_map(fn ($tx) => $tx->externalId, $result->transactions);

        $this->assertSame([
            'nubank-fitid-001',
            'nubank-fitid-002',
            'nubank-fitid-003',
        ], $ids);
    }

    public function test_malformed_ofx_throws_invalid_statement_without_absolute_path(): void
    {
        try {
            (new OfxParser)->parse($this->fixture('sample_malformed.ofx'));
            $this->fail('Expected InvalidStatementException');
        } catch (InvalidStatementException $e) {
            $this->assertStringNotContainsString('D:\\', $e->getMessage());
            $this->assertStringNotContainsString('/Projetos/', $e->getMessage());
            $this->assertStringContainsString('OFX', $e->getMessage());
        }
    }

    public function test_unreadable_file_throws_invalid_statement(): void
    {
        $this->expectException(InvalidStatementException::class);

        (new OfxParser)->parse($this->fixture('does_not_exist.ofx'));
    }
}

<?php

namespace Tests\Unit\Parsers;

use App\Exceptions\InvalidStatementException;
use App\Parsers\NubankCsvParser;
use Tests\TestCase;

class NubankCsvParserFormatTest extends TestCase
{
    private function fixture(string $name): string
    {
        return base_path('tests/Fixtures/statements/nubank/'.$name);
    }

    public function test_supports_nubank_csv_only(): void
    {
        $parser = new NubankCsvParser;

        $this->assertTrue($parser->supports('csv', 'nubank'));
        $this->assertFalse($parser->supports('ofx', 'nubank'));
        $this->assertFalse($parser->supports('csv', 'other'));
    }

    public function test_parses_mvp_account_profile_fixture(): void
    {
        $result = (new NubankCsvParser)->parse($this->fixture('sample_account.csv'));

        $this->assertSame('csv', $result->format);
        $this->assertSame('nubank', $result->source);
        $this->assertSame(5, $result->rowsTotal);
        $this->assertCount(4, $result->transactions); // zero row skipped
        $this->assertTrue($result->hasRowErrors());

        $first = $result->transactions[0];
        $this->assertSame('2026-09-01', $first->occurredOn);
        $this->assertSame('89.90', $first->amount);
        $this->assertSame('debit', $first->type);
        $this->assertSame('abc-001', $first->externalId);

        $credit = $result->transactions[1];
        $this->assertSame('1250.00', $credit->amount);
        $this->assertSame('credit', $credit->type);

        $quotedDesc = $result->transactions[2];
        $this->assertSame('Padaria, Café & Cia', $quotedDesc->description);
    }

    public function test_accepts_utf8_bom_header(): void
    {
        $result = (new NubankCsvParser)->parse($this->fixture('sample_account_bom.csv'));

        $this->assertGreaterThan(0, count($result->transactions));
        $this->assertSame('2026-09-01', $result->transactions[0]->occurredOn);
    }

    public function test_autodetects_semicolon_delimiter(): void
    {
        $result = (new NubankCsvParser)->parse($this->fixture('sample_account_semicolon.csv'));

        $this->assertCount(2, $result->transactions);
        $this->assertSame('89.90', $result->transactions[0]->amount);
        $this->assertSame('1250.00', $result->transactions[1]->amount);
    }

    public function test_missing_required_header_throws_invalid_statement(): void
    {
        $this->expectException(InvalidStatementException::class);
        $this->expectExceptionMessage('Cabeçalho CSV inválido');

        (new NubankCsvParser)->parse($this->fixture('sample_account_missing_header.csv'));
    }

    public function test_skips_empty_lines_between_rows(): void
    {
        $path = storage_path('framework/testing/nubank_empty_lines.csv');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, "Data,Valor,Descrição\n\n01/09/2026,\"-10,00\",Teste\n\n");

        try {
            $result = (new NubankCsvParser)->parse($path);
            $this->assertCount(1, $result->transactions);
            $this->assertSame(1, $result->rowsTotal);
        } finally {
            @unlink($path);
        }
    }
}

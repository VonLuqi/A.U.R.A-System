<?php

namespace Tests\Unit\Parsers;

use App\DTOs\ParsedTransaction;
use App\Parsers\Contracts\StatementParserInterface;
use App\Parsers\NubankCsvParser;
use SplFileInfo;
use Tests\TestCase;

/**
 * §3.4.3 — implementação: interface, supports, fgetcsv, ParsedTransaction, rawPayload original.
 */
class NubankCsvParserImplementationTest extends TestCase
{
    private function fixture(string $name): string
    {
        return base_path('tests/Fixtures/statements/nubank/'.$name);
    }

    public function test_implements_statement_parser_interface(): void
    {
        $parser = new NubankCsvParser;

        $this->assertInstanceOf(StatementParserInterface::class, $parser);
    }

    public function test_supports_csv_nubank_only(): void
    {
        $parser = new NubankCsvParser;

        $this->assertTrue($parser->supports('csv', 'nubank'));
        $this->assertFalse($parser->supports('ofx', 'nubank'));
        $this->assertFalse($parser->supports('csv', 'inter'));
    }

    public function test_parses_via_spl_file_info_and_yields_parsed_transactions(): void
    {
        $file = new SplFileInfo($this->fixture('sample_account.csv'));
        $result = (new NubankCsvParser)->parse($file);

        $this->assertNotEmpty($result->transactions);
        foreach ($result->transactions as $tx) {
            $this->assertInstanceOf(ParsedTransaction::class, $tx);
        }
    }

    public function test_raw_payload_uses_original_header_labels(): void
    {
        $result = (new NubankCsvParser)->parse($this->fixture('sample_account.csv'));
        $first = $result->transactions[0];

        $this->assertArrayHasKey('Data', $first->rawPayload);
        $this->assertArrayHasKey('Valor', $first->rawPayload);
        $this->assertArrayHasKey('Descrição', $first->rawPayload);
        $this->assertArrayHasKey('Identificador', $first->rawPayload);

        $this->assertSame('01/09/2026', $first->rawPayload['Data']);
        $this->assertSame('-89,90', $first->rawPayload['Valor']);
        $this->assertSame('abc-001', $first->rawPayload['Identificador']);
        $this->assertSame('Supermercado Extra', $first->rawPayload['Descrição']);

        // Must not leak normalized keys into rawPayload
        $this->assertArrayNotHasKey('data', $first->rawPayload);
        $this->assertArrayNotHasKey('valor', $first->rawPayload);
        $this->assertArrayNotHasKey('descricao', $first->rawPayload);
    }

    public function test_uses_native_fgetcsv_not_league_csv(): void
    {
        $source = file_get_contents((new \ReflectionClass(NubankCsvParser::class))->getFileName());

        $this->assertStringContainsString('fgetcsv(', $source);
        $this->assertDoesNotMatchRegularExpression('/^use\s+League\\\\Csv/m', $source);
        $this->assertStringNotContainsString('league/csv', $source);
    }
}

<?php

namespace Tests\Unit\Parsers;

use App\Exceptions\InvalidStatementException;
use App\Parsers\NubankCsvParser;
use Tests\TestCase;

/**
 * §7.3 — NubankCsvParser acceptance (fixture snapshot + money/date/BOM/errors).
 *
 * Granular coverage remains in NubankCsvParser{Format,Type,Implementation,Challenges}Test (§3.4).
 */
class NubankCsvParserTest extends TestCase
{
    private function fixture(string $name): string
    {
        return base_path('tests/Fixtures/statements/nubank/'.$name);
    }

    private function parseInline(string $body): \App\DTOs\ParseResult
    {
        $path = storage_path('framework/testing/nubank_7_3_'.uniqid('', true).'.csv');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $body);

        try {
            return (new NubankCsvParser)->parse($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_parse_fixture_snapshot_count_first_and_last_row(): void
    {
        $result = (new NubankCsvParser)->parse($this->fixture('sample_account.csv'));

        // 5 data rows; zero skipped → 4 transactions + 1 rowError
        $this->assertSame(5, $result->rowsTotal);
        $this->assertCount(4, $result->transactions);
        $this->assertTrue($result->hasRowErrors());

        $first = $result->transactions[0];
        $this->assertSame('2026-09-01', $first->occurredOn);
        $this->assertSame('89.90', $first->amount);
        $this->assertSame('debit', $first->type);
        $this->assertSame('Supermercado Extra', $first->description);
        $this->assertSame('abc-001', $first->externalId);

        $last = $result->transactions[array_key_last($result->transactions)];
        $this->assertSame('2026-09-05', $last->occurredOn);
        $this->assertSame('10.00', $last->amount);
        $this->assertSame('debit', $last->type);
        $this->assertSame('Transferência sem id', $last->description);
        $this->assertNull($last->externalId);
    }

    public function test_negative_brazilian_thousands_becomes_debit_absolute(): void
    {
        $result = $this->parseInline(
            "Data,Valor,Descrição\n27/09/2026,\"-1.234,56\",Compra debitada\n"
        );

        $this->assertCount(1, $result->transactions);
        $this->assertSame('1234.56', $result->transactions[0]->amount);
        $this->assertSame('debit', $result->transactions[0]->type);
    }

    public function test_positive_ten_reais_becomes_credit(): void
    {
        $result = $this->parseInline(
            "Data,Valor,Descrição\n27/09/2026,\"10,00\",Pagamento recebido\n"
        );

        $this->assertCount(1, $result->transactions);
        $this->assertSame('10.00', $result->transactions[0]->amount);
        $this->assertSame('credit', $result->transactions[0]->type);
    }

    public function test_brazilian_date_normalizes_to_iso(): void
    {
        $result = $this->parseInline(
            "Data,Valor,Descrição\n27/09/2026,\"-10,00\",Data check\n"
        );

        $this->assertSame('2026-09-27', $result->transactions[0]->occurredOn);
    }

    public function test_utf8_bom_does_not_break_header(): void
    {
        $result = (new NubankCsvParser)->parse($this->fixture('sample_account_bom.csv'));

        $this->assertGreaterThan(0, count($result->transactions));
        $this->assertSame('2026-09-01', $result->transactions[0]->occurredOn);
        $this->assertSame('debit', $result->transactions[0]->type);
    }

    public function test_invalid_row_increments_row_errors_without_aborting(): void
    {
        $result = (new NubankCsvParser)->parse($this->fixture('sample_account_invalid_rows.csv'));

        $this->assertSame(6, $result->rowsTotal);
        $this->assertCount(4, $result->transactions);
        $this->assertTrue($result->hasRowErrors());
        $this->assertGreaterThanOrEqual(2, count($result->rowErrors));

        $last = $result->transactions[array_key_last($result->transactions)];
        $this->assertSame('Crédito final', $last->description);
        $this->assertSame('credit', $last->type);
    }

    public function test_missing_header_throws_invalid_statement(): void
    {
        $this->expectException(InvalidStatementException::class);
        $this->expectExceptionMessage('Cabeçalho CSV inválido');

        (new NubankCsvParser)->parse($this->fixture('sample_account_missing_header.csv'));
    }
}

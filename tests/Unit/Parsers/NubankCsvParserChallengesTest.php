<?php

namespace Tests\Unit\Parsers;

use App\Parsers\NubankCsvParser;
use App\Support\TransactionHasher;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * §3.4.4 — desafios obrigatórios de tratamento (money, data, quotes, short rows, hash contract).
 */
class NubankCsvParserChallengesTest extends TestCase
{
    private function fixture(string $name): string
    {
        return base_path('tests/Fixtures/statements/nubank/'.$name);
    }

    public function test_sanitizes_brazilian_money_with_thousands_separator(): void
    {
        $result = (new NubankCsvParser)->parse($this->fixture('sample_account_invalid_rows.csv'));

        $first = $result->transactions[0];
        $this->assertSame('1234.56', $first->amount);
        $this->assertSame('debit', $first->type);
        $this->assertSame('-1.234,56', $first->rawPayload['Valor']);
    }

    public function test_normalizes_brazilian_dates_to_sql(): void
    {
        $result = (new NubankCsvParser)->parse($this->fixture('sample_account.csv'));

        $this->assertSame('2026-09-01', $result->transactions[0]->occurredOn);
        $this->assertSame('2026-09-02', $result->transactions[1]->occurredOn);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $result->transactions[0]->occurredOn);
    }

    public function test_preserves_descriptions_with_commas_and_quotes(): void
    {
        $result = (new NubankCsvParser)->parse($this->fixture('sample_account_invalid_rows.csv'));

        $quoted = $result->transactions[1];
        $this->assertSame('Padaria, Café & Cia', $quoted->description);
        $this->assertSame('Padaria, Café & Cia', $quoted->rawPayload['Descrição']);
    }

    public function test_short_or_invalid_rows_become_row_errors_without_aborting(): void
    {
        $result = (new NubankCsvParser)->parse($this->fixture('sample_account_invalid_rows.csv'));

        // 6 data rows total; 2 invalid (short + bad money); 4 valid transactions
        $this->assertSame(6, $result->rowsTotal);
        $this->assertCount(4, $result->transactions);
        $this->assertTrue($result->hasRowErrors());
        $this->assertGreaterThanOrEqual(2, count($result->rowErrors));

        $messages = array_column($result->rowErrors, 'message');
        $this->assertContains('Linha incompleta: colunas obrigatórias ausentes.', $messages);

        $last = $result->transactions[array_key_last($result->transactions)];
        $this->assertSame('Crédito final', $last->description);
        $this->assertSame('credit', $last->type);
    }

    public function test_parser_does_not_generate_unique_hash(): void
    {
        $result = (new NubankCsvParser)->parse($this->fixture('sample_account.csv'));
        $tx = $result->transactions[0];

        $this->assertObjectNotHasProperty('uniqueHash', $tx);
        $this->assertObjectNotHasProperty('unique_hash', $tx);

        $source = file_get_contents((new ReflectionClass(NubankCsvParser::class))->getFileName());
        $this->assertStringNotContainsString('TransactionHasher', $source);
    }

    public function test_transaction_hasher_excludes_statement_import_id_and_is_cross_import(): void
    {
        $params = array_map(
            fn ($p) => $p->getName(),
            (new ReflectionMethod(TransactionHasher::class, 'make'))->getParameters()
        );

        $this->assertNotContains('statementImportId', $params);
        $this->assertNotContains('statement_import_id', $params);
        $this->assertContains('source', $params);

        // Same logical row across two imports → same hash (cross-import idempotency)
        $hashA = TransactionHasher::make('2026-09-01', '89.90', 'debit', 'Supermercado Extra', 'abc-001', 'nubank');
        $hashB = TransactionHasher::make('2026-09-01', '89.90', 'debit', 'Supermercado Extra', 'abc-001', 'nubank');
        $this->assertSame($hashA, $hashB);

        // Changing only an imaginary import id must not be possible via API — hash stays source-scoped
        $this->assertSame(64, strlen($hashA));
    }
}

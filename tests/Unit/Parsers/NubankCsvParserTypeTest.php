<?php

namespace Tests\Unit\Parsers;

use App\Parsers\NubankCsvParser;
use Tests\TestCase;

/**
 * §3.4.2 — regras de tipo (sinal → credit|debit; zero → skip).
 */
class NubankCsvParserTypeTest extends TestCase
{
    private function parseCsv(string $body): \App\DTOs\ParseResult
    {
        $path = storage_path('framework/testing/nubank_type_'.uniqid('', true).'.csv');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $body);

        try {
            return (new NubankCsvParser)->parse($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_negative_value_becomes_debit_with_absolute_amount(): void
    {
        $result = $this->parseCsv(
            "Data,Valor,Descrição\n27/09/2026,\"-1.234,56\",Compra debitada\n"
        );

        $this->assertCount(1, $result->transactions);
        $tx = $result->transactions[0];
        $this->assertSame('debit', $tx->type);
        $this->assertSame('1234.56', $tx->amount);
        $this->assertFalse(str_starts_with($tx->amount, '-'));
    }

    public function test_positive_value_becomes_credit_with_absolute_amount(): void
    {
        $result = $this->parseCsv(
            "Data,Valor,Descrição\n27/09/2026,\"10,00\",Pagamento recebido\n"
        );

        $this->assertCount(1, $result->transactions);
        $tx = $result->transactions[0];
        $this->assertSame('credit', $tx->type);
        $this->assertSame('10.00', $tx->amount);
    }

    public function test_zero_value_is_skipped_with_row_error(): void
    {
        $result = $this->parseCsv(
            "Data,Valor,Descrição\n".
            "01/09/2026,\"-5,00\",Debito\n".
            "02/09/2026,\"0,00\",Ajuste zero\n".
            "03/09/2026,\"1,00\",Credito\n"
        );

        $this->assertSame(3, $result->rowsTotal);
        $this->assertCount(2, $result->transactions);
        $this->assertTrue($result->hasRowErrors());
        $this->assertCount(1, $result->rowErrors);
        $this->assertSame(3, $result->rowErrors[0]['line']); // header=1, zero=line 3
        $this->assertSame('Valor zero ignorado.', $result->rowErrors[0]['message']);

        $this->assertSame('debit', $result->transactions[0]->type);
        $this->assertSame('5.00', $result->transactions[0]->amount);
        $this->assertSame('credit', $result->transactions[1]->type);
        $this->assertSame('1.00', $result->transactions[1]->amount);
    }

    public function test_sample_fixture_maps_signs_and_skips_zero(): void
    {
        $result = (new NubankCsvParser)->parse(
            base_path('tests/Fixtures/statements/nubank/sample_account.csv')
        );

        $types = array_map(fn ($tx) => $tx->type, $result->transactions);
        $amounts = array_map(fn ($tx) => $tx->amount, $result->transactions);

        $this->assertSame(['debit', 'credit', 'debit', 'debit'], $types);
        $this->assertSame(['89.90', '1250.00', '15.50', '10.00'], $amounts);
        $this->assertContains('Valor zero ignorado.', array_column($result->rowErrors, 'message'));
    }
}

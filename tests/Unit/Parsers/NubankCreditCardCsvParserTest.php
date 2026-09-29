<?php

namespace Tests\Unit\Parsers;

use App\Exceptions\InvalidStatementException;
use App\Parsers\NubankCreditCardCsvParser;
use App\Parsers\NubankCsvParser;
use App\Parsers\StatementParserResolver;
use Tests\TestCase;

/**
 * PLAN_EXPANSAO §5.2 — unit coverage for credit-card CSV parser.
 */
class NubankCreditCardCsvParserTest extends TestCase
{
    private function fixture(): string
    {
        return base_path('tests/Fixtures/statements/nubank_credit_card_sample.csv');
    }

    public function test_supports_csv_credit_card_formats(): void
    {
        $parser = new NubankCreditCardCsvParser;

        $this->assertTrue($parser->supports('csv_credit_card', 'nubank_credit'));
        $this->assertTrue($parser->supports('csv_credit_card', 'nubank'));
        $this->assertTrue($parser->supports('csv_credit_card', 'other'));
        $this->assertFalse($parser->supports('csv', 'nubank'));
        $this->assertFalse($parser->supports('ofx', 'nubank_credit'));
    }

    public function test_parses_fixture_with_correct_types_dates_and_absolute_amounts(): void
    {
        $parser = new NubankCreditCardCsvParser;
        $result = $parser->parse($this->fixture());

        $this->assertSame('csv_credit_card', $result->format);
        $this->assertSame('nubank_credit', $result->source);
        $this->assertSame(9, $result->rowsTotal);
        $this->assertCount(7, $result->transactions);
        $this->assertCount(2, $result->rowErrors);

        $expected = [
            ['Mercado Extra', '2026-09-01', '89.90', 'debit'],
            ['Spotify Ab', '2026-09-02', '34.90', 'debit'],
            ['Uber *Trip', '2026-09-03', '18.90', 'debit'],
            ['Estorno Uber *Trip', '2026-09-04', '18.90', 'credit'],
            ['Magazine Luiza 1/12', '2026-09-05', '127.42', 'debit'],
            ['IOF Notion Labs', '2026-09-07', '2.78', 'debit'],
            ['iFood *Pedido', '2026-09-08', '45.50', 'debit'],
        ];

        foreach ($result->transactions as $index => $tx) {
            [$description, $date, $amount, $type] = $expected[$index];
            $this->assertSame($description, $tx->description);
            $this->assertSame($date, $tx->occurredOn);
            $this->assertSame($amount, $tx->amount);
            $this->assertSame($type, $tx->type);
            $this->assertDoesNotMatchRegularExpression('/^-/', $tx->amount);
            $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $tx->amount);
            $this->assertSame('nubank_credit', $tx->rawPayload['card_source']);
        }

        $this->assertSame('supermercado', $result->transactions[0]->rawPayload['nubank_category']);
    }

    public function test_skips_pagamento_recebido_and_total_rows(): void
    {
        $result = (new NubankCreditCardCsvParser)->parse($this->fixture());

        $descriptions = array_map(fn ($tx) => $tx->description, $result->transactions);

        $this->assertNotContains('Pagamento recebido', $descriptions);
        $this->assertNotContains('Total fatura', $descriptions);
        $this->assertTrue($result->hasRowErrors());
    }

    public function test_accepts_brazilian_headers_and_money(): void
    {
        $path = storage_path('framework/testing/nubank_cc_br.csv');
        @mkdir(dirname($path), 0777, true);
        file_put_contents(
            $path,
            "Data,Descrição,Valor\n".
            "01/09/2026,Compra Loja,\"89,90\"\n".
            "02/09/2026,Estorno Loja,\"-10,00\"\n".
            "03/09/2026,Pagamento recebido,\"-100,00\"\n"
        );

        try {
            $result = (new NubankCreditCardCsvParser)->parse($path);

            $this->assertCount(2, $result->transactions);
            $this->assertSame('2026-09-01', $result->transactions[0]->occurredOn);
            $this->assertSame('89.90', $result->transactions[0]->amount);
            $this->assertSame('debit', $result->transactions[0]->type);
            $this->assertSame('credit', $result->transactions[1]->type);
            $this->assertSame('10.00', $result->transactions[1]->amount);
        } finally {
            @unlink($path);
        }
    }

    public function test_missing_required_header_throws_invalid_statement(): void
    {
        $path = storage_path('framework/testing/nubank_cc_bad_header.csv');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, "foo,bar\n1,2\n");

        try {
            $this->expectException(InvalidStatementException::class);
            $this->expectExceptionMessage('Cabeçalho CSV de cartão inválido');
            (new NubankCreditCardCsvParser)->parse($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_checking_account_parser_rejects_credit_card_headers_clearly(): void
    {
        $this->expectException(InvalidStatementException::class);
        $this->expectExceptionMessage('Cabeçalho CSV inválido');

        (new NubankCsvParser)->parse($this->fixture());
    }

    public function test_container_resolver_picks_credit_card_parser(): void
    {
        $resolver = $this->app->make(StatementParserResolver::class);

        $this->assertInstanceOf(
            NubankCreditCardCsvParser::class,
            $resolver->resolve('csv_credit_card', 'nubank_credit')
        );
        $this->assertInstanceOf(
            NubankCsvParser::class,
            $resolver->resolve('csv', 'nubank')
        );
    }
}

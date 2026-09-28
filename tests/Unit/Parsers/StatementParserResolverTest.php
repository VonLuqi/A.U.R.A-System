<?php

namespace Tests\Unit\Parsers;

use App\Exceptions\UnsupportedStatementFormatException;
use App\Parsers\NubankCsvParser;
use App\Parsers\OfxParser;
use App\Parsers\StatementParserResolver;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StatementParserResolverTest extends TestCase
{
    public function test_resolves_nubank_csv_parser(): void
    {
        $resolver = new StatementParserResolver([
            new NubankCsvParser,
            new OfxParser,
        ]);

        $parser = $resolver->resolve('csv', 'nubank');

        $this->assertInstanceOf(NubankCsvParser::class, $parser);
    }

    public function test_resolves_ofx_and_qfx_to_ofx_parser(): void
    {
        $resolver = new StatementParserResolver([
            new NubankCsvParser,
            new OfxParser,
        ]);

        $this->assertInstanceOf(OfxParser::class, $resolver->resolve('ofx', 'nubank'));
        $this->assertInstanceOf(OfxParser::class, $resolver->resolve('qfx', 'nubank'));
    }

    public function test_unsupported_format_throws_domain_exception(): void
    {
        $resolver = new StatementParserResolver([
            new NubankCsvParser,
            new OfxParser,
        ]);

        try {
            $resolver->resolve('pdf', 'nubank');
            $this->fail('Expected UnsupportedStatementFormatException');
        } catch (UnsupportedStatementFormatException $e) {
            $this->assertSame('pdf', $e->format);
            $this->assertSame('nubank', $e->source);
            $this->assertSame('unsupported_format', $e->errorCode);
            $this->assertStringContainsString('não suportado', $e->getMessage());
        }
    }

    public function test_container_binding_returns_singleton_with_both_parsers(): void
    {
        $a = $this->app->make(StatementParserResolver::class);
        $b = $this->app->make(StatementParserResolver::class);

        $this->assertSame($a, $b);
        $this->assertInstanceOf(NubankCsvParser::class, $a->resolve('csv', 'nubank'));
        $this->assertInstanceOf(OfxParser::class, $a->resolve('ofx', 'nubank'));
    }

    public function test_unsupported_format_renders_as_json_422(): void
    {
        Route::middleware('web')->get('/api/__resolver_probe_unsupported', function () {
            throw new UnsupportedStatementFormatException(
                message: 'Formato de extrato não suportado: pdf/nubank.',
                format: 'pdf',
                source: 'nubank',
            );
        });

        $this->getJson('/api/__resolver_probe_unsupported')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'unsupported_format')
            ->assertJsonPath('format', 'pdf')
            ->assertJsonPath('source', 'nubank')
            ->assertJsonPath('message', 'Formato de extrato não suportado: pdf/nubank.');
    }
}

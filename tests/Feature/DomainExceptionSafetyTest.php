<?php

namespace Tests\Feature;

use App\Exceptions\InvalidStatementException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * §4.4 — domain exceptions + production-safe API error payloads (APP_DEBUG=false).
 */
class DomainExceptionSafetyTest extends TestCase
{
    public function test_invalid_statement_exception_returns_422_without_path_leak(): void
    {
        Route::middleware('web')->post('/api/__domain_invalid_probe', function () {
            throw new InvalidStatementException(
                'Falha ao ler D:\\Projetos\\ControleFinanceiroPessoal\\storage\\app\\private\\x.csv'
            );
        });

        $response = $this->postJson('/api/__domain_invalid_probe');

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'invalid_statement')
            ->assertJsonMissing(['file', 'trace', 'exception']);

        $body = $response->getContent();
        $this->assertStringNotContainsString('D:\\Projetos', $body);
        $this->assertStringContainsString('[path]', $body);
    }

    public function test_query_exception_hides_sqlstate_when_debug_false(): void
    {
        config(['app.debug' => false]);

        Route::middleware('web')->post('/api/__domain_sql_probe', function () {
            throw new QueryException(
                'mysql',
                'insert into `transactions` (`unique_hash`) values (?)',
                ['abc'],
                new \PDOException('SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry')
            );
        });

        $response = $this->postJson('/api/__domain_sql_probe');

        $response->assertStatus(500)
            ->assertJsonPath('message', 'Não foi possível processar a solicitação.')
            ->assertJsonMissing(['exception', 'file', 'trace', 'sql']);

        $body = $response->getContent();
        $this->assertStringNotContainsString('SQLSTATE', $body);
        $this->assertStringNotContainsString('unique_hash', $body);
        $this->assertStringNotContainsString('Duplicate entry', $body);
    }

    public function test_generic_exception_hides_trace_and_paths_when_debug_false(): void
    {
        config(['app.debug' => false]);

        Route::middleware('web')->post('/api/__domain_generic_probe', function () {
            throw new \RuntimeException(
                'Boom at D:\\Projetos\\ControleFinanceiroPessoal\\app\\Services\\StatementUploadService.php:120'
            );
        });

        $response = $this->postJson('/api/__domain_generic_probe');

        $response->assertStatus(500)
            ->assertJsonPath('message', 'Não foi possível processar a solicitação.')
            ->assertJsonMissing(['exception', 'file', 'trace', 'line']);

        $body = $response->getContent();
        $this->assertStringNotContainsString('RuntimeException', $body);
        $this->assertStringNotContainsString('D:\\Projetos', $body);
        $this->assertStringNotContainsString('StatementUploadService.php', $body);
    }

    public function test_generic_exception_may_include_detail_when_debug_true(): void
    {
        config(['app.debug' => true]);

        Route::middleware('web')->post('/api/__domain_debug_probe', function () {
            throw new \RuntimeException('Visible debug message');
        });

        $response = $this->postJson('/api/__domain_debug_probe');

        $response->assertStatus(500);
        $this->assertStringContainsString('Visible debug message', $response->getContent());
    }
}

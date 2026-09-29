<?php

namespace Tests\Feature\Statements;

use App\DTOs\UploadSummary;
use App\Exceptions\InvalidStatementException;
use App\Models\StatementImport;
use App\Models\Transaction;
use App\Models\User;
use App\Services\StatementUploadService;
use App\Support\StatementStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * §4.1 — StatementUploadService orchestration (store → import → parse → persist → summary).
 */
class StatementUploadServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(StatementStorage::DISK);
    }

    private function service(): StatementUploadService
    {
        return $this->app->make(StatementUploadService::class);
    }

    private function uploadedFixture(string $relativeFixture, string $clientName): UploadedFile
    {
        $path = base_path('tests/Fixtures/statements/'.$relativeFixture);

        return new UploadedFile($path, $clientName, 'text/csv', null, true);
    }

    public function test_store_step_detects_format_persists_relative_path_checksum_and_sanitized_name(): void
    {
        $user = User::factory()->create();
        $raw = base_path('tests/Fixtures/statements/nubank/sample_account.csv');
        // Path traversal in client name must be stripped to basename-safe form.
        $file = new UploadedFile($raw, '..\\..\\evil\\NU Setembro.csv', 'text/csv', null, true);

        $summary = $this->service()->handle($user, $file);

        $import = StatementImport::query()->findOrFail($summary->importId);

        $this->assertSame('csv', $import->format);
        $this->assertSame('NU-Setembro.csv', $import->original_filename);
        $this->assertMatchesRegularExpression(
            '#^\d{4}/\d{2}/'.$user->id.'/[0-9a-f-]{36}_NU-Setembro\.csv$#',
            $import->stored_path
        );
        $this->assertStringNotContainsString('..', $import->stored_path);
        $this->assertStringNotContainsString(storage_path(), $import->stored_path);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $import->checksum);
        $this->assertTrue(Storage::disk(StatementStorage::DISK)->exists($import->stored_path));

        $absolute = Storage::disk(StatementStorage::DISK)->path($import->stored_path);
        $this->assertSame(hash_file('sha256', $absolute), $import->checksum);
    }

    public function test_creates_import_as_processing_with_zero_counters_after_store(): void
    {
        $user = User::factory()->create();
        $file = $this->uploadedFixture('nubank/sample_account.csv', 'NU_sample.csv');

        $created = null;
        StatementImport::creating(function (StatementImport $import) use (&$created): void {
            $created = [
                'status' => $import->status,
                'rows_total' => $import->rows_total,
                'rows_imported' => $import->rows_imported,
                'rows_skipped' => $import->rows_skipped,
                'stored_path' => $import->stored_path,
                'checksum' => $import->checksum,
                'error_message' => $import->error_message,
            ];
        });

        $this->service()->handle($user, $file);

        $this->assertNotNull($created);
        $this->assertSame('processing', $created['status']);
        $this->assertSame(0, $created['rows_total']);
        $this->assertSame(0, $created['rows_imported']);
        $this->assertSame(0, $created['rows_skipped']);
        $this->assertNotNull($created['stored_path']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $created['checksum']);
        $this->assertNull($created['error_message']);
    }

    public function test_store_failure_does_not_create_import_row(): void
    {
        Storage::clearResolvedInstances();

        $filesystem = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $filesystem->shouldReceive('putFileAs')
            ->once()
            ->andThrow(new \RuntimeException('Unable to write statement file.'));

        Storage::shouldReceive('disk')
            ->with(StatementStorage::DISK)
            ->andReturn($filesystem);

        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent(
            'nubank.csv',
            "Data,Valor,Descrição\n01/09/2026,\"-10,00\",Teste\n"
        );

        try {
            $this->service()->handle($user, $file);
            $this->fail('Expected RuntimeException from store');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Unable to write', $e->getMessage());
            $this->assertSame(0, StatementImport::query()->count());
            $this->assertSame(0, Transaction::query()->count());
        }
    }

    public function test_handle_orchestrates_store_import_parse_persist_and_summary(): void
    {
        $user = User::factory()->create();
        $file = $this->uploadedFixture('nubank/sample_account.csv', 'NU_sample.csv');

        $summary = $this->service()->handle($user, $file, 'nubank');

        $this->assertInstanceOf(UploadSummary::class, $summary);
        $this->assertSame('completed', $summary->status);
        $this->assertSame('csv', $summary->format);
        $this->assertSame('nubank', $summary->source);
        $this->assertSame('NU_sample.csv', $summary->originalFilename);
        $this->assertSame(5, $summary->rowsTotal);
        $this->assertSame(4, $summary->rowsImported); // zero row skipped as rowError
        $this->assertGreaterThanOrEqual(1, $summary->rowsSkipped);
        $this->assertNotSame('', $summary->checksum);

        $import = StatementImport::query()->findOrFail($summary->importId);
        $this->assertSame('completed', $import->status);
        $this->assertNotNull($import->stored_path);
        $this->assertTrue(Storage::disk(StatementStorage::DISK)->exists($import->stored_path));
        $this->assertSame(4, Transaction::query()->where('statement_import_id', $import->id)->count());
        $this->assertNull(Transaction::query()->where('statement_import_id', $import->id)->value('category_id'));
    }

    public function test_invalid_parse_marks_import_failed_and_keeps_file(): void
    {
        $user = User::factory()->create();
        $file = $this->uploadedFixture('nubank/sample_account_missing_header.csv', 'bad.csv');

        try {
            $this->service()->handle($user, $file);
            $this->fail('Expected InvalidStatementException');
        } catch (InvalidStatementException $e) {
            $import = StatementImport::query()->first();
            $this->assertNotNull($import);
            $this->assertSame('failed', $import->status);
            $this->assertNotNull($import->error_message);
            $this->assertLessThanOrEqual(1000, mb_strlen($import->error_message));
            $this->assertStringNotContainsString('D:\\', $import->error_message);
            $this->assertStringNotContainsString(storage_path(), $import->error_message);
            $this->assertTrue(Storage::disk(StatementStorage::DISK)->exists($import->stored_path));
            $this->assertSame(0, Transaction::query()->count());
        }
    }

    public function test_parse_failure_sanitizes_paths_and_truncates_error_message(): void
    {
        $user = User::factory()->create();
        $file = $this->uploadedFixture('nubank/sample_account.csv', 'ok.csv');

        $longTail = str_repeat('x', 1200);
        $parser = \Mockery::mock(\App\Parsers\Contracts\StatementParserInterface::class);
        $parser->shouldReceive('supports')->andReturnUsing(
            fn (string $format, string $source) => $format === 'csv' && $source === 'nubank'
        );
        $parser->shouldReceive('parse')
            ->once()
            ->andThrow(new InvalidStatementException(
                'Falha em D:\\Projetos\\ControleFinanceiroPessoal\\storage\\app\\private\\statements\\x.csv '.$longTail
            ));

        $service = new StatementUploadService(
            new \App\Parsers\StatementParserResolver([$parser]),
            $this->app->make(\App\Services\UsageLimitService::class),
            $this->app->make(\App\Services\AliasResolutionService::class),
        );

        try {
            $service->handle($user, $file);
            $this->fail('Expected InvalidStatementException');
        } catch (InvalidStatementException $e) {
            $import = StatementImport::query()->firstOrFail();
            $this->assertSame('failed', $import->status);
            $this->assertSame(1000, mb_strlen($import->error_message));
            $this->assertStringContainsString('[path]', $import->error_message);
            $this->assertStringNotContainsString('D:\\Projetos', $import->error_message);
            $this->assertTrue(Storage::disk(StatementStorage::DISK)->exists($import->stored_path));
            // Exception itself is also path-sanitized by InvalidStatementException.
            $this->assertStringNotContainsString('D:\\Projetos', $e->getMessage());
        }
    }

    public function test_parse_invalid_statement_exception_renders_as_json_422(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')->post('/api/__upload_parse_probe', function () {
            throw new InvalidStatementException('Não foi possível ler o extrato.');
        });

        $this->postJson('/api/__upload_parse_probe')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'invalid_statement')
            ->assertJsonPath('message', 'Não foi possível ler o extrato.');
    }

    public function test_atomic_persist_sets_counters_hashes_and_null_category(): void
    {
        $user = User::factory()->create();
        $file = $this->uploadedFixture('nubank/sample_account.csv', 'NU_sample.csv');

        $summary = $this->service()->handle($user, $file);

        $import = StatementImport::query()->findOrFail($summary->importId);
        $this->assertSame('completed', $import->status);
        $this->assertSame(5, $import->rows_total);
        $this->assertSame(4, $import->rows_imported);
        $this->assertSame(1, $import->rows_skipped); // zero-value rowError only
        $this->assertSame(
            $import->rows_imported + $import->rows_skipped,
            $import->rows_total
        );

        $transactions = Transaction::query()
            ->where('statement_import_id', $import->id)
            ->orderBy('occurred_on')
            ->get();

        $this->assertCount(4, $transactions);
        foreach ($transactions as $tx) {
            $this->assertNull($tx->category_id);
            $this->assertSame(64, strlen($tx->unique_hash));
            $expected = \App\Support\TransactionHasher::make(
                $tx->occurred_on->format('Y-m-d'),
                $tx->amount,
                $tx->type,
                $tx->description,
                $tx->external_id,
                'nubank',
            );
            $this->assertSame($expected, $tx->unique_hash);
        }
    }

    public function test_persist_failure_rolls_back_transactions_and_marks_import_failed(): void
    {
        $user = User::factory()->create();
        $file = $this->uploadedFixture('nubank/sample_account.csv', 'NU_sample.csv');

        StatementImport::updating(function (StatementImport $import): void {
            if ($import->status === 'completed') {
                throw new \RuntimeException('Simulated persist failure');
            }
        });

        try {
            $this->service()->handle($user, $file);
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Simulated persist failure', $e->getMessage());
            $import = StatementImport::query()->firstOrFail();
            $this->assertSame('failed', $import->status);
            $this->assertNotNull($import->error_message);
            $this->assertSame(0, Transaction::query()->where('statement_import_id', $import->id)->count());
            $this->assertTrue(Storage::disk(StatementStorage::DISK)->exists($import->stored_path));
        }
    }

    public function test_reupload_skips_duplicate_hashes_without_blocking_checksum(): void
    {
        $user = User::factory()->create();
        $file1 = $this->uploadedFixture('nubank/sample_account.csv', 'NU_sample.csv');
        $file2 = $this->uploadedFixture('nubank/sample_account.csv', 'NU_sample.csv');

        $first = $this->service()->handle($user, $file1);
        $second = $this->service()->handle($user, $file2);

        // Same checksum must NOT block a second import (no UNIQUE on checksum).
        $this->assertSame($first->checksum, $second->checksum);
        $this->assertNotSame($first->importId, $second->importId);
        $this->assertSame(2, StatementImport::query()->count());

        // Line-level dedupe via unique_hash — partial success, not an error.
        $this->assertSame('completed', $second->status);
        $this->assertSame(0, $second->rowsImported);
        $this->assertSame(5, $second->rowsSkipped); // 4 dup hashes + 1 zero rowError
        $this->assertSame(5, $second->rowsTotal);
        $this->assertSame(4, Transaction::query()->count());
        $this->assertSame(
            4,
            Transaction::query()->distinct()->count('unique_hash')
        );

        // Summary remains a successful UploadSummary (HTTP 2xx wiring is §5.3).
        $payload = $second->toArray();
        $this->assertSame(0, $payload['rows_imported']);
        $this->assertSame(5, $payload['rows_skipped']);
        $this->assertSame('completed', $payload['status']);
    }
}

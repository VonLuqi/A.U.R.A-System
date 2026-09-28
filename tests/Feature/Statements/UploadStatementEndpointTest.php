<?php

namespace Tests\Feature\Statements;

use App\Models\StatementImport;
use App\Models\Transaction;
use App\Models\User;
use App\Support\StatementStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * §5.3 / §7.7 — StatementUploadController@store (201 + 422 + reupload + OFX + disk).
 */
class UploadStatementEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(StatementStorage::DISK);
    }

    private function uploadedFixture(string $relative, string $clientName): UploadedFile
    {
        return new UploadedFile(
            base_path('tests/Fixtures/statements/'.$relative),
            $clientName,
            'text/plain',
            null,
            true,
        );
    }

    public function test_upload_returns_201_with_summary_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/statements/upload', [
            'file' => $this->uploadedFixture('nubank/sample_account.csv', 'NU_123.csv'),
            'source' => 'nubank',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.format', 'csv')
            ->assertJsonPath('data.source', 'nubank')
            ->assertJsonPath('data.original_filename', 'NU_123.csv')
            ->assertJsonPath('data.rows_total', 5)
            ->assertJsonPath('data.rows_imported', 4)
            ->assertJsonPath('data.rows_skipped', 1)
            ->assertJsonPath('data.row_errors_count', 1)
            ->assertJsonStructure([
                'data' => [
                    'import_id',
                    'status',
                    'format',
                    'source',
                    'original_filename',
                    'checksum',
                    'rows_total',
                    'rows_imported',
                    'rows_skipped',
                    'row_errors_count',
                    'row_errors',
                ],
            ]);

        $this->assertLessThanOrEqual(20, count($response->json('data.row_errors')));
        $this->assertSame(4, Transaction::query()->count());
        $this->assertSame(1, StatementImport::query()->count());

        $import = StatementImport::query()->findOrFail($response->json('data.import_id'));
        $this->assertNotEmpty($import->stored_path);
        $this->assertTrue(
            Storage::disk(StatementStorage::DISK)->exists($import->stored_path),
            'Uploaded fixture must exist on the statements disk'
        );
    }

    public function test_reupload_same_file_skips_duplicates_without_extra_hashes(): void
    {
        $user = User::factory()->create();
        $payload = [
            'file' => $this->uploadedFixture('nubank/sample_account.csv', 'NU_123.csv'),
            'source' => 'nubank',
        ];

        $first = $this->actingAs($user)->postJson('/api/statements/upload', $payload);
        $first->assertCreated()
            ->assertJsonPath('data.rows_imported', 4);

        // Fresh UploadedFile instance (same fixture bytes).
        $second = $this->actingAs($user)->postJson('/api/statements/upload', [
            'file' => $this->uploadedFixture('nubank/sample_account.csv', 'NU_123.csv'),
            'source' => 'nubank',
        ]);

        $second->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.rows_imported', 0)
            ->assertJsonPath('data.rows_skipped', 5);

        $this->assertSame(2, StatementImport::query()->count());
        $this->assertSame(4, Transaction::query()->count());
        $this->assertSame(4, Transaction::query()->distinct()->count('unique_hash'));
    }

    public function test_ofx_happy_path_imports_rows_and_stores_file(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/statements/upload', [
            'file' => $this->uploadedFixture('ofx/sample_nubank.ofx', 'nubank.ofx'),
            'source' => 'nubank',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.format', 'ofx')
            ->assertJsonPath('data.source', 'nubank')
            ->assertJsonPath('data.rows_imported', 3);

        $this->assertSame(3, Transaction::query()->count());

        $import = StatementImport::query()->findOrFail($response->json('data.import_id'));
        $this->assertTrue(Storage::disk(StatementStorage::DISK)->exists($import->stored_path));
    }

    public function test_upload_parse_failure_returns_422_with_import_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/statements/upload', [
            'file' => $this->uploadedFixture('nubank/sample_account_missing_header.csv', 'bad.csv'),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'invalid_statement')
            ->assertJsonStructure(['message', 'error_code', 'import_id']);

        $importId = $response->json('import_id');
        $this->assertIsInt($importId);
        $this->assertSame('failed', StatementImport::query()->findOrFail($importId)->status);
        $this->assertSame(0, Transaction::query()->count());
    }

    public function test_row_errors_are_truncated_to_20_in_response(): void
    {
        $user = User::factory()->create();

        // Build a CSV with many incomplete rows (rowErrors) plus one valid.
        $lines = ["Data,Valor,Descrição"];
        for ($i = 1; $i <= 25; $i++) {
            $lines[] = sprintf('0%d/09/2026', ($i % 9) + 1); // incomplete → rowError
        }
        $lines[] = '27/09/2026,"-10,00",Linha valida';

        $response = $this->actingAs($user)->postJson('/api/statements/upload', [
            'file' => UploadedFile::fake()->createWithContent(
                'many.csv',
                implode("\n", $lines)."\n"
            ),
        ]);

        $response->assertCreated();
        $this->assertSame(25, $response->json('data.row_errors_count'));
        $this->assertCount(20, $response->json('data.row_errors'));
    }
}

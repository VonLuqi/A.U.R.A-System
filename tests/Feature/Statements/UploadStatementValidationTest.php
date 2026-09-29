<?php

namespace Tests\Feature\Statements;

use App\Models\User;
use App\Support\StatementStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadStatementValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Storage::fake(StatementStorage::DISK);
    }

    public function test_upload_requires_authentication(): void
    {
        $this->postJson('/api/statements/upload', [
            'file' => UploadedFile::fake()->create('nubank.csv', 10, 'text/csv'),
        ])->assertUnauthorized();
    }

    public function test_upload_requires_file(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/statements/upload', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file'])
            ->assertJsonPath('errors.file.0', 'Envie um arquivo de extrato.');
    }

    public function test_upload_rejects_disallowed_extension(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/statements/upload', [
                'file' => UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_upload_rejects_invalid_source(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/statements/upload', [
                'file' => UploadedFile::fake()->create('nubank.csv', 10, 'text/csv'),
                'source' => 'inter',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source'])
            ->assertJsonPath('errors.source.0', 'Fonte inválida. Use nubank, nubank_credit ou other.');
    }

    public function test_upload_accepts_nubank_credit_and_other_sources(): void
    {
        $user = User::factory()->create();
        $file = new UploadedFile(
            base_path('tests/Fixtures/statements/nubank/sample_account.csv'),
            'nubank.csv',
            'text/plain',
            null,
            true,
        );

        // `other` uses the checking-account CSV parser (same headers).
        $this->actingAs($user)
            ->postJson('/api/statements/upload', [
                'file' => $file,
                'source' => 'other',
            ])
            ->assertCreated()
            ->assertJsonPath('data.source', 'other')
            ->assertJsonPath('data.format', 'csv');

        // `nubank_credit` + credit-card fixture → csv_credit_card parser (§5.1).
        $this->actingAs($user)
            ->postJson('/api/statements/upload', [
                'file' => new UploadedFile(
                    base_path('tests/Fixtures/statements/nubank_credit_card_sample.csv'),
                    'nubank-credit.csv',
                    'text/plain',
                    null,
                    true,
                ),
                'source' => 'nubank_credit',
            ])
            ->assertCreated()
            ->assertJsonPath('data.source', 'nubank_credit')
            ->assertJsonPath('data.format', 'csv_credit_card')
            ->assertJsonPath('data.rows_imported', 7);
    }

    public function test_upload_statement_kind_credit_card_override(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/statements/upload', [
                'file' => new UploadedFile(
                    base_path('tests/Fixtures/statements/nubank_credit_card_sample.csv'),
                    'fatura.csv',
                    'text/plain',
                    null,
                    true,
                ),
                'statement_kind' => 'credit_card',
            ])
            ->assertCreated()
            ->assertJsonPath('data.format', 'csv_credit_card')
            ->assertJsonPath('data.source', 'nubank_credit');
    }

    public function test_upload_rejects_invalid_statement_kind(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/statements/upload', [
                'file' => UploadedFile::fake()->create('nubank.csv', 10, 'text/csv'),
                'statement_kind' => 'savings',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['statement_kind'])
            ->assertJsonPath(
                'errors.statement_kind.0',
                'Tipo de extrato inválido. Use checking ou credit_card.'
            );
    }

    public function test_upload_accepts_csv_ofx_and_qfx_extensions(): void
    {
        $user = User::factory()->create();

        $cases = [
            ['nubank/sample_account.csv', 'nubank.csv', 'csv'],
            ['ofx/sample_nubank.ofx', 'nubank.ofx', 'ofx'],
            ['ofx/sample_nubank.ofx', 'nubank.qfx', 'ofx'],
        ];

        foreach ($cases as [$fixture, $clientName, $format]) {
            $this->actingAs($user)
                ->postJson('/api/statements/upload', [
                    'file' => new UploadedFile(
                        base_path('tests/Fixtures/statements/'.$fixture),
                        $clientName,
                        'text/plain',
                        null,
                        true,
                    ),
                    'source' => 'nubank',
                ])
                ->assertCreated()
                ->assertJsonPath('data.format', $format)
                ->assertJsonPath('data.source', 'nubank');
        }
    }

    public function test_upload_rejects_file_larger_than_10mb(): void
    {
        $user = User::factory()->create();

        // max:10240 is KB; create slightly over 10 MB.
        $this->actingAs($user)
            ->postJson('/api/statements/upload', [
                'file' => UploadedFile::fake()->create('huge.csv', 10241, 'text/csv'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file'])
            ->assertJsonPath('errors.file.0', 'O arquivo deve ter no máximo 10 MB.');
    }
}

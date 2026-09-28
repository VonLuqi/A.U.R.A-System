<?php

namespace Tests\Feature;

use App\Exceptions\InvalidStatementException;
use App\Models\StatementImport;
use App\Models\User;
use App\Support\StatementStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * §5.9 — canonical HTTP error payloads for Etapa D.
 */
class HttpErrorContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Storage::fake(StatementStorage::DISK);
    }

    public function test_unauthenticated_returns_401_json(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_validation_returns_422_with_errors(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/statements/upload', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['file']]);
    }

    public function test_invalid_parse_returns_422_with_error_code(): void
    {
        Route::middleware('web')->post('/api/__http_error_parse_probe', function () {
            throw (new InvalidStatementException('Não foi possível ler o extrato.'))->withImportId(42);
        });

        $this->postJson('/api/__http_error_parse_probe')
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'invalid_statement')
            ->assertJsonPath('import_id', 42)
            ->assertJsonStructure(['message', 'error_code', 'import_id']);
    }

    public function test_rate_limit_returns_429_with_retry_after(): void
    {
        $limit = (int) env('RATE_LIMIT_LOGIN_PER_EMAIL', 5);

        for ($i = 0; $i < $limit; $i++) {
            $this->postJson('/api/login', [
                'email' => 'ratelimit-contract@aura.local',
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/login', [
            'email' => 'ratelimit-contract@aura.local',
            'password' => 'wrong-password',
        ])
            ->assertStatus(429)
            ->assertJsonStructure(['message'])
            ->assertHeader('Retry-After');
    }

    public function test_missing_or_foreign_import_returns_404(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $import = StatementImport::factory()->for($owner)->create();

        $this->actingAs($other)
            ->getJson('/api/statements/'.$import->id)
            ->assertNotFound();

        $this->actingAs($owner)
            ->getJson('/api/statements/999999')
            ->assertNotFound();
    }

    public function test_unexpected_error_returns_500_generic_when_debug_false(): void
    {
        config(['app.debug' => false]);

        Route::middleware('web')->post('/api/__http_error_500_probe', function () {
            throw new \RuntimeException('secret internal detail');
        });

        $this->postJson('/api/__http_error_500_probe')
            ->assertStatus(500)
            ->assertExactJson([
                'message' => 'Não foi possível processar a solicitação.',
            ]);
    }

    public function test_upload_rejects_invalid_file_as_422_validation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/statements/upload', [
                'file' => UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }
}

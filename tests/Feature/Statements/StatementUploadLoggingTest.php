<?php

namespace Tests\Feature\Statements;

use App\Models\StatementImport;
use App\Models\User;
use App\Support\StatementStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * §8 — upload observability logs (no row payloads).
 */
class StatementUploadLoggingTest extends TestCase
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

    public function test_successful_upload_logs_counters_without_row_content(): void
    {
        Event::fake([MessageLogged::class]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/statements/upload', [
            'file' => $this->uploadedFixture('nubank/sample_account.csv', 'NU_123.csv'),
        ])->assertCreated();

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $log) use ($user): bool {
            if ($log->level !== 'info' || $log->message !== 'statements.upload.completed') {
                return false;
            }

            $context = $log->context;
            $this->assertSame($user->id, $context['user_id']);
            $this->assertArrayHasKey('import_id', $context);
            $this->assertSame('csv', $context['format']);
            $this->assertArrayHasKey('rows_total', $context);
            $this->assertArrayHasKey('rows_imported', $context);
            $this->assertArrayHasKey('rows_skipped', $context);
            $this->assertArrayHasKey('checksum', $context);
            $this->assertArrayNotHasKey('raw_payload', $context);
            $this->assertArrayNotHasKey('row_errors', $context);
            $this->assertArrayNotHasKey('transactions', $context);

            return true;
        });
    }

    public function test_failed_upload_logs_warning_with_error_message(): void
    {
        Event::fake([MessageLogged::class]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/statements/upload', [
            'file' => $this->uploadedFixture('nubank/sample_account_missing_header.csv', 'bad.csv'),
        ])->assertStatus(422);

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $log): bool {
            if ($log->level !== 'warning' || $log->message !== 'statements.upload.failed') {
                return false;
            }

            $context = $log->context;
            $this->assertArrayHasKey('user_id', $context);
            $this->assertArrayHasKey('import_id', $context);
            $this->assertArrayHasKey('format', $context);
            $this->assertArrayHasKey('checksum', $context);
            $this->assertArrayHasKey('error_message', $context);
            $this->assertNotSame('', (string) $context['error_message']);
            $this->assertArrayNotHasKey('raw_payload', $context);

            $import = StatementImport::query()->findOrFail($context['import_id']);
            $this->assertSame('failed', $import->status);
            $this->assertSame($import->error_message, $context['error_message']);

            return true;
        });
    }
}

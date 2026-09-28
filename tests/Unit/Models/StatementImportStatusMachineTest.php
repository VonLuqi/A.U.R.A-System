<?php

namespace Tests\Unit\Models;

use App\Models\StatementImport;
use App\Models\User;
use App\Services\StatementUploadService;
use App\Support\StatementStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * §4.5 — statement_imports status machine.
 */
class StatementImportStatusMachineTest extends TestCase
{
    use RefreshDatabase;

    public function test_mvp_skips_pending_and_creates_processing_then_completed(): void
    {
        Storage::fake(StatementStorage::DISK);

        $seen = [];
        StatementImport::creating(function (StatementImport $import) use (&$seen): void {
            $seen[] = $import->status;
        });

        $user = User::factory()->create();
        $path = base_path('tests/Fixtures/statements/nubank/sample_account.csv');
        $file = new UploadedFile($path, 'NU.csv', 'text/csv', null, true);

        $summary = $this->app->make(StatementUploadService::class)->handle($user, $file);

        $this->assertSame([StatementImport::STATUS_PROCESSING], $seen);
        $this->assertNotContains(StatementImport::STATUS_PENDING, $seen);
        $this->assertSame(StatementImport::STATUS_COMPLETED, $summary->status);

        $import = StatementImport::query()->findOrFail($summary->importId);
        $this->assertTrue($import->isTerminal());
        $this->assertFalse($import->canTransitionTo(StatementImport::STATUS_PROCESSING));
        $this->assertFalse($import->canTransitionTo(StatementImport::STATUS_FAILED));
    }

    public function test_parse_failure_ends_in_failed_terminal_state(): void
    {
        Storage::fake(StatementStorage::DISK);

        $user = User::factory()->create();
        $path = base_path('tests/Fixtures/statements/nubank/sample_account_missing_header.csv');
        $file = new UploadedFile($path, 'bad.csv', 'text/csv', null, true);

        try {
            $this->app->make(StatementUploadService::class)->handle($user, $file);
            $this->fail('Expected parse failure');
        } catch (\Throwable) {
            $import = StatementImport::query()->firstOrFail();
            $this->assertSame(StatementImport::STATUS_FAILED, $import->status);
            $this->assertTrue($import->isTerminal());
            // No auto-reprocess: failed cannot transition back to processing.
            $this->assertFalse($import->canTransitionTo(StatementImport::STATUS_PROCESSING));
            $this->assertFalse($import->canTransitionTo(StatementImport::STATUS_COMPLETED));
        }
    }

    public function test_transition_rules_match_status_machine(): void
    {
        $user = User::factory()->create();

        $pending = StatementImport::factory()->for($user)->create([
            'status' => StatementImport::STATUS_PENDING,
        ]);
        $this->assertTrue($pending->canTransitionTo(StatementImport::STATUS_PROCESSING));
        $this->assertFalse($pending->canTransitionTo(StatementImport::STATUS_COMPLETED));

        $pending->transitionTo(StatementImport::STATUS_PROCESSING);
        $this->assertSame(StatementImport::STATUS_PROCESSING, $pending->fresh()->status);

        $processing = $pending->fresh();
        $this->assertTrue($processing->canTransitionTo(StatementImport::STATUS_COMPLETED));
        $this->assertTrue($processing->canTransitionTo(StatementImport::STATUS_FAILED));

        $this->expectException(InvalidArgumentException::class);
        $processing->transitionTo(StatementImport::STATUS_PENDING);
    }

    public function test_terminal_states_reject_reprocess_transitions(): void
    {
        $user = User::factory()->create();

        $completed = StatementImport::factory()->for($user)->create([
            'status' => StatementImport::STATUS_COMPLETED,
        ]);
        $failed = StatementImport::factory()->for($user)->failed()->create();

        $this->assertSame([], StatementImport::TRANSITIONS[StatementImport::STATUS_COMPLETED]);
        $this->assertSame([], StatementImport::TRANSITIONS[StatementImport::STATUS_FAILED]);
        $this->assertFalse($completed->canTransitionTo(StatementImport::STATUS_PROCESSING));
        $this->assertFalse($failed->canTransitionTo(StatementImport::STATUS_PROCESSING));
    }
}

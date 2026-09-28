<?php

namespace Tests\Feature\Statements;

use App\Models\StatementImport;
use App\Models\Transaction;
use App\Models\User;
use App\Support\StatementStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StatementRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_lists_candidates_without_deleting(): void
    {
        Storage::fake(StatementStorage::DISK);

        $user = User::factory()->create();
        $path = '2026/01/1/old_nubank.csv';
        Storage::disk(StatementStorage::DISK)->put($path, "Data,Valor\n");

        StatementImport::factory()->for($user)->create([
            'stored_path' => $path,
            'status' => 'completed',
            'created_at' => now()->subDays(120),
            'updated_at' => now()->subDays(120),
        ]);

        Artisan::call('statements:purge-files', ['--days' => 90, '--dry-run' => true]);

        $this->assertTrue(Storage::disk(StatementStorage::DISK)->exists($path));
        $this->assertNull(StatementImport::query()->first()->purged_at);
    }

    public function test_purge_deletes_file_marks_purged_at_and_keeps_transactions(): void
    {
        Storage::fake(StatementStorage::DISK);

        $user = User::factory()->create();
        $path = '2025/12/1/old_nubank.csv';
        Storage::disk(StatementStorage::DISK)->put($path, "Data,Valor\n");

        $import = StatementImport::factory()->for($user)->create([
            'stored_path' => $path,
            'status' => 'completed',
            'created_at' => now()->subDays(100),
            'updated_at' => now()->subDays(100),
        ]);

        $tx = Transaction::factory()->for($import)->create();

        Artisan::call('statements:purge-files', ['--days' => 90]);

        $import->refresh();

        $this->assertFalse(Storage::disk(StatementStorage::DISK)->exists($path));
        $this->assertNotNull($import->purged_at);
        $this->assertSame($path, $import->stored_path); // audit trail kept
        $this->assertTrue(Transaction::query()->whereKey($tx->id)->exists());
        $this->assertTrue(StatementImport::query()->whereKey($import->id)->exists());
    }

    public function test_recent_imports_are_not_purged(): void
    {
        Storage::fake(StatementStorage::DISK);

        $path = '2026/09/1/recent.csv';
        Storage::disk(StatementStorage::DISK)->put($path, 'x');

        StatementImport::factory()->create([
            'stored_path' => $path,
            'status' => 'completed',
            'created_at' => now()->subDays(10),
        ]);

        Artisan::call('statements:purge-files', ['--days' => 90]);

        $this->assertTrue(Storage::disk(StatementStorage::DISK)->exists($path));
        $this->assertNull(StatementImport::query()->first()->purged_at);
    }
}

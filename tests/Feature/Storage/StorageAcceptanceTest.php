<?php

namespace Tests\Feature\Storage;

use App\Models\StatementImport;
use App\Models\Transaction;
use App\Models\User;
use App\Support\StatementStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Etapa C §2.5 — Storage acceptance criteria.
 */
class StorageAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_helper_stores_under_private_statements_disk(): void
    {
        Storage::fake(StatementStorage::DISK);

        $file = UploadedFile::fake()->createWithContent(
            'nubank-setembro.csv',
            "Data,Valor\n01/09/2026,-10,00\n"
        );

        $result = StatementStorage::storeUploadedFile($file, userId: 1);

        $this->assertTrue(Storage::disk(StatementStorage::DISK)->exists($result['stored_path']));
        $this->assertMatchesRegularExpression(
            '#^\d{4}/\d{2}/1/[0-9a-f-]{36}_nubank-setembro\.csv$#',
            $result['stored_path']
        );
        $this->assertSame(
            storage_path('app/private/statements'),
            config('filesystems.disks.statements.root')
        );
        $this->assertStringNotContainsString(storage_path(), $result['stored_path']);
    }

    public function test_public_storage_url_returns_404_for_statement_files(): void
    {
        Storage::disk('statements')->put('smoke/secret.csv', "Data,Valor\n");

        try {
            $this->get('/storage/smoke/secret.csv')->assertNotFound();
            $this->get('/storage/statements/smoke/secret.csv')->assertNotFound();
            $this->get('/storage/app/private/statements/smoke/secret.csv')->assertNotFound();
        } finally {
            Storage::disk('statements')->delete('smoke/secret.csv');
        }
    }

    public function test_purge_dry_run_lists_candidates_without_deleting(): void
    {
        Storage::fake(StatementStorage::DISK);

        $path = '2026/01/1/old.csv';
        Storage::disk(StatementStorage::DISK)->put($path, 'x');

        StatementImport::factory()->create([
            'stored_path' => $path,
            'status' => 'completed',
            'created_at' => now()->subDays(120),
            'updated_at' => now()->subDays(120),
        ]);

        $exit = Artisan::call('statements:purge-files', [
            '--days' => 90,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Dry-run:', Artisan::output());
        $this->assertTrue(Storage::disk(StatementStorage::DISK)->exists($path));
        $this->assertNull(StatementImport::query()->value('purged_at'));
    }

    public function test_after_purge_import_remains_listable_and_transactions_intact(): void
    {
        Storage::fake(StatementStorage::DISK);

        $user = User::factory()->create();
        $path = '2025/11/1/old.csv';
        Storage::disk(StatementStorage::DISK)->put($path, 'x');

        $import = StatementImport::factory()->for($user)->create([
            'stored_path' => $path,
            'status' => 'completed',
            'created_at' => now()->subDays(100),
            'updated_at' => now()->subDays(100),
        ]);

        $tx = Transaction::factory()->for($import)->create([
            'description' => 'Kept after purge',
        ]);

        Artisan::call('statements:purge-files', ['--days' => 90]);

        $import->refresh();

        $this->assertFalse(Storage::disk(StatementStorage::DISK)->exists($path));
        $this->assertNotNull($import->purged_at);

        $listed = StatementImport::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->get(['id', 'original_filename', 'status', 'purged_at', 'stored_path']);

        $this->assertTrue($listed->contains('id', $import->id));
        $this->assertTrue(Transaction::query()->whereKey($tx->id)->exists());
        $this->assertSame('Kept after purge', $tx->fresh()->description);
    }
}

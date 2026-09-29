<?php

namespace Tests\Feature\Transactions;

use App\Enums\TransactionSourceKind;
use App\Models\StatementImport;
use App\Models\Transaction;
use App\Models\User;
use App\Support\TransactionHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransactionMultiTenantSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_sets_user_id_from_statement_import(): void
    {
        $user = User::factory()->create();
        $import = StatementImport::factory()->for($user)->create();

        $tx = Transaction::factory()->for($import, 'statementImport')->create();

        $this->assertSame($user->id, $tx->user_id);
        $this->assertSame(TransactionSourceKind::Import, $tx->source_kind);
        $this->assertTrue($user->transactions()->whereKey($tx->id)->exists());
    }

    public function test_scope_for_user_isolates_rows(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $owned = Transaction::factory()
            ->for(StatementImport::factory()->for($owner), 'statementImport')
            ->create();
        Transaction::factory()
            ->for(StatementImport::factory()->for($other), 'statementImport')
            ->create();

        $ids = Transaction::query()->forUser($owner)->pluck('id')->all();

        $this->assertSame([$owned->id], $ids);
    }

    public function test_unique_hash_is_scoped_per_user(): void
    {
        $hash = TransactionHasher::make('2026-01-15', '10.00', 'debit', 'Same row', 'ext-1', 'nubank');

        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Transaction::factory()
            ->for(StatementImport::factory()->for($userA), 'statementImport')
            ->create(['unique_hash' => $hash, 'user_id' => $userA->id]);

        Transaction::factory()
            ->for(StatementImport::factory()->for($userB), 'statementImport')
            ->create(['unique_hash' => $hash, 'user_id' => $userB->id]);

        $this->assertSame(2, Transaction::query()->where('unique_hash', $hash)->count());
    }

    public function test_backfill_command_is_idempotent_when_already_populated(): void
    {
        $user = User::factory()->create();
        $tx = Transaction::factory()
            ->for(StatementImport::factory()->for($user), 'statementImport')
            ->create();

        $this->assertSame($user->id, $tx->fresh()->user_id);

        $exit = Artisan::call('aura:backfill-transaction-user-id');

        $this->assertSame(0, $exit);
        $this->assertSame($user->id, $tx->fresh()->user_id);
        $this->assertStringContainsString('Backfilled user_id on 0 transaction', Artisan::output());
    }

    public function test_manual_factory_state_allows_null_import(): void
    {
        $user = User::factory()->create();

        $tx = Transaction::factory()->manual()->for($user)->create();

        $this->assertSame($user->id, $tx->user_id);
        $this->assertNull($tx->statement_import_id);
        $this->assertSame(TransactionSourceKind::Manual, $tx->source_kind);
    }

    public function test_user_id_column_is_not_nullable_after_migrations(): void
    {
        $column = collect(DB::select('SHOW COLUMNS FROM transactions LIKE \'user_id\''))->first();

        $this->assertNotNull($column);
        $this->assertSame('NO', strtoupper((string) $column->Null));
    }
}

<?php

namespace Tests\Feature\Transactions;

use App\Enums\AliasMatchType;
use App\Enums\GoalStatus;
use App\Enums\TransactionSourceKind;
use App\Models\Category;
use App\Models\Goal;
use App\Models\Transaction;
use App\Models\TransactionAlias;
use App\Models\User;
use App\Services\UsageLimitService;
use Database\Seeders\RoleLimitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PLAN_EXPANSAO §3.1 — manual transaction CRUD endpoints.
 */
class ManualTransactionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleLimitsSeeder::class);
    }

    public function test_user_creates_manual_transaction_and_increments_quota(): void
    {
        $user = User::factory()->visitor()->create([
            'manual_transactions_used' => 0,
            'quota_period_starts_at' => now()->startOfMonth(),
        ]);
        $category = Category::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/transactions', [
                'occurred_on' => '2026-09-15',
                'amount' => 42.5,
                'type' => 'debit',
                'description' => 'Farmácia manual',
                'category_id' => $category->id,
                'notes' => 'sem cupom',
            ])
            ->assertCreated()
            ->assertJsonPath('data.description', 'Farmácia manual')
            ->assertJsonPath('data.source_kind', 'manual')
            ->assertJsonPath('data.statement_import_id', null)
            ->assertJsonPath('data.amount', '42.50')
            ->assertJsonPath('data.notes', 'sem cupom')
            ->assertJsonPath('data.editable', true)
            ->assertJsonPath('data.deletable', true);

        $this->assertSame(1, $user->fresh()->manual_transactions_used);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'source_kind' => TransactionSourceKind::Manual->value,
            'description' => 'Farmácia manual',
        ]);
    }

    public function test_store_returns_429_when_manual_quota_exhausted(): void
    {
        $user = User::factory()->visitor()->create([
            'manual_transactions_used' => 20,
            'quota_period_starts_at' => now()->startOfMonth(),
        ]);

        $this->actingAs($user)
            ->postJson('/api/transactions', [
                'occurred_on' => '2026-09-15',
                'amount' => 10,
                'type' => 'debit',
                'description' => 'Over quota',
            ])
            ->assertStatus(429)
            ->assertJsonPath('error_code', 'usage_limit_exceeded')
            ->assertJsonPath('metric', UsageLimitService::METRIC_MANUAL_TRANSACTIONS);
    }

    public function test_show_update_delete_manual_transaction(): void
    {
        $user = User::factory()->subadmin()->create();
        $tx = Transaction::factory()->manual()->for($user)->create([
            'description' => 'Original',
            'amount' => '10.00',
            'type' => 'debit',
            'occurred_on' => '2026-09-10',
        ]);

        $this->actingAs($user)
            ->getJson('/api/transactions/'.$tx->id)
            ->assertOk()
            ->assertJsonPath('data.id', $tx->id)
            ->assertJsonPath('data.source_kind', 'manual');

        $this->actingAs($user)
            ->patchJson('/api/transactions/'.$tx->id, [
                'description' => 'Atualizado',
                'amount' => 15.75,
            ])
            ->assertOk()
            ->assertJsonPath('data.description', 'Atualizado')
            ->assertJsonPath('data.amount', '15.75');

        $this->actingAs($user)
            ->deleteJson('/api/transactions/'.$tx->id)
            ->assertOk()
            ->assertJsonPath('message', 'Lançamento excluído.');

        $this->assertDatabaseMissing('transactions', ['id' => $tx->id]);
    }

    public function test_imported_transaction_blocks_core_field_updates_for_non_admin(): void
    {
        $user = User::factory()->visitor()->create();
        $tx = Transaction::factory()->for($user)->create([
            'source_kind' => TransactionSourceKind::Import,
            'amount' => '50.00',
        ]);
        $category = Category::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/transactions/'.$tx->id, [
                'amount' => 99,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);

        $this->actingAs($user)
            ->patchJson('/api/transactions/'.$tx->id, [
                'category_id' => $category->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.category.id', $category->id);
    }

    public function test_other_user_gets_404_on_foreign_transaction(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $tx = Transaction::factory()->manual()->for($owner)->create();

        $this->actingAs($other)
            ->getJson('/api/transactions/'.$tx->id)
            ->assertNotFound();

        $this->actingAs($other)
            ->patchJson('/api/transactions/'.$tx->id, ['description' => 'hack'])
            ->assertNotFound();

        $this->actingAs($other)
            ->deleteJson('/api/transactions/'.$tx->id)
            ->assertNotFound();
    }

    public function test_rejects_invalid_store_payload(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/transactions', [
                'occurred_on' => '15/09/2026',
                'amount' => 0,
                'type' => 'transfer',
                'description' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['occurred_on', 'amount', 'type', 'description']);
    }

    public function test_manual_create_auto_applies_alias(): void
    {
        $user = User::factory()->visitor()->create([
            'manual_transactions_used' => 0,
            'quota_period_starts_at' => now()->startOfMonth(),
        ]);
        $category = Category::factory()->create();

        TransactionAlias::factory()->for($user)->withCategory($category)->create([
            'match_type' => AliasMatchType::Contains,
            'match_pattern' => 'DROGA RAIA',
            'display_name' => 'Farmácia',
            'priority' => 1,
        ]);

        $this->actingAs($user)
            ->postJson('/api/transactions', [
                'occurred_on' => '2026-09-20',
                'amount' => 33.1,
                'type' => 'debit',
                'description' => 'DROGA RAIA CENTRO',
            ])
            ->assertCreated()
            ->assertJsonPath('data.description', 'Farmácia')
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.notes', null);

        $tx = Transaction::query()->forUser($user)->firstOrFail();
        $this->assertSame('DROGA RAIA CENTRO', $tx->raw_payload['original_description']);
        $this->assertArrayHasKey('alias_id', $tx->raw_payload);
    }

    public function test_creating_manual_transaction_updates_linked_goal(): void
    {
        $user = User::factory()->visitor()->create([
            'manual_transactions_used' => 0,
            'quota_period_starts_at' => now()->startOfMonth(),
        ]);
        $category = Category::factory()->create();

        $goal = Goal::factory()->savings()->for($user)->create([
            'category_id' => $category->id,
            'linked_description_pattern' => '%reserva%',
            'target_amount' => '100.00',
            'current_amount' => '0.00',
            'created_at' => now()->subMonth(),
        ]);

        $this->actingAs($user)
            ->postJson('/api/transactions', [
                'occurred_on' => now()->toDateString(),
                'amount' => 100,
                'type' => 'credit',
                'description' => 'reserva emergência',
                'category_id' => $category->id,
            ])
            ->assertCreated();

        $goal->refresh();
        $this->assertSame('100.00', (string) $goal->current_amount);
        $this->assertSame(GoalStatus::Completed, $goal->status);
    }

    public function test_manual_create_appears_on_dashboard_and_transactions_list(): void
    {
        $user = User::factory()->visitor()->create([
            'manual_transactions_used' => 0,
            'quota_period_starts_at' => now()->startOfMonth(),
        ]);

        $this->actingAs($user)
            ->postJson('/api/transactions', [
                'occurred_on' => '2026-09-18',
                'amount' => 75.5,
                'type' => 'debit',
                'description' => 'Padaria manual filtrada',
            ])
            ->assertCreated()
            ->assertJsonPath('data.source_kind', 'manual');

        $this->actingAs($user)
            ->getJson('/api/transactions?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.description', 'Padaria manual filtrada')
            ->assertJsonPath('data.0.source_kind', 'manual');

        $this->actingAs($user)
            ->getJson('/api/analytics/dashboard?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonPath('data.cards.transactions_count', 1)
            ->assertJsonPath('data.cards.total_expense', '75.50')
            ->assertJsonPath('data.cards.total_income', '0.00');
    }

    public function test_manual_and_import_with_same_fields_coexist_for_same_user(): void
    {
        $user = User::factory()->admin()->create();
        $import = \App\Models\StatementImport::factory()->for($user)->create();

        $importHash = \App\Support\TransactionHasher::make(
            '2026-09-20',
            '15.00',
            'debit',
            'Café Igual',
            null,
            'nubank',
        );

        Transaction::factory()->for($import, 'statementImport')->create([
            'user_id' => $user->id,
            'occurred_on' => '2026-09-20',
            'amount' => '15.00',
            'type' => 'debit',
            'description' => 'Café Igual',
            'unique_hash' => $importHash,
            'source_kind' => TransactionSourceKind::Import,
        ]);

        $this->actingAs($user)
            ->postJson('/api/transactions', [
                'occurred_on' => '2026-09-20',
                'amount' => 15,
                'type' => 'debit',
                'description' => 'Café Igual',
            ])
            ->assertCreated()
            ->assertJsonPath('data.source_kind', 'manual');

        $this->assertSame(2, Transaction::query()->forUser($user)->count());
        $hashes = Transaction::query()->forUser($user)->pluck('unique_hash')->unique()->count();
        $this->assertSame(2, $hashes);
    }
}

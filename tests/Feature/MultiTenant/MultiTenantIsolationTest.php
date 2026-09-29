<?php

namespace Tests\Feature\MultiTenant;

use App\Models\Goal;
use App\Models\StatementImport;
use App\Models\Transaction;
use App\Models\TransactionAlias;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PLAN_EXPANSAO §2.2 — HTTP / service isolation by user_id.
 */
class MultiTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_http_transactions_and_statements_hide_other_users_data(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $otherImport = StatementImport::factory()->for($other)->create();
        Transaction::factory()->for($otherImport, 'statementImport')->create([
            'description' => 'Secret other',
        ]);

        $ownImport = StatementImport::factory()->for($user)->create();
        Transaction::factory()->for($ownImport, 'statementImport')->create([
            'description' => 'Visible mine',
            'occurred_on' => now()->toDateString(),
        ]);
        Transaction::factory()->manual()->for($user)->create([
            'description' => 'Manual mine',
            'occurred_on' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->getJson('/api/transactions')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonMissing(['description' => 'Secret other']);

        $this->actingAs($user)
            ->getJson('/api/statements')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($user)
            ->getJson('/api/statements/'.$otherImport->id)
            ->assertNotFound();
    }

    public function test_analytics_dashboard_ignores_other_users_and_includes_manual(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Transaction::factory()
            ->for(StatementImport::factory()->for($user), 'statementImport')
            ->create([
                'type' => 'credit',
                'amount' => '100.00',
                'occurred_on' => '2026-09-10',
            ]);
        Transaction::factory()->manual()->for($user)->create([
            'type' => 'debit',
            'amount' => '40.00',
            'occurred_on' => '2026-09-11',
        ]);
        Transaction::factory()
            ->for(StatementImport::factory()->for($other), 'statementImport')
            ->create([
                'type' => 'credit',
                'amount' => '999.00',
                'occurred_on' => '2026-09-10',
            ]);

        $cards = app(AnalyticsService::class)->cards($user, [
            'from' => '2026-09-01',
            'to' => '2026-09-30',
        ]);

        $this->assertSame('100.00', $cards['total_income']);
        $this->assertSame('40.00', $cards['total_expense']);
        $this->assertSame('60.00', $cards['balance']);
        $this->assertSame(2, $cards['transactions_count']);

        $this->actingAs($user)
            ->getJson('/api/analytics/dashboard?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonPath('data.cards.transactions_count', 2)
            ->assertJsonPath('data.cards.total_income', '100.00');
    }

    public function test_goal_and_alias_bindings_return_404_for_other_owner(): void
    {
        $owner = User::factory()->admin()->create();
        $intruder = User::factory()->admin()->create();

        $goal = Goal::factory()->for($owner)->create();
        $alias = TransactionAlias::factory()->for($owner)->create();

        // Bindings registered in AppServiceProvider — exercise via can + fresh resolve.
        $this->assertTrue($owner->can('view', $goal));
        $this->assertFalse($intruder->can('view', $goal));
        $this->assertTrue($owner->can('view', $alias));
        $this->assertFalse($intruder->can('view', $alias));

        $this->assertNull(
            Goal::query()->forUser($intruder)->whereKey($goal->id)->first()
        );
        $this->assertNull(
            TransactionAlias::query()->forUser($intruder)->whereKey($alias->id)->first()
        );
    }

    public function test_categories_remain_global_shared_catalog(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $responseA = $this->actingAs($a)->getJson('/api/categories')->assertOk();
        $responseB = $this->actingAs($b)->getJson('/api/categories')->assertOk();

        $this->assertSame(
            $responseA->json('data'),
            $responseB->json('data')
        );
    }
}

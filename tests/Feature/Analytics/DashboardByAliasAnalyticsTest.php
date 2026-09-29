<?php

namespace Tests\Feature\Analytics;

use App\Models\Transaction;
use App\Models\TransactionAlias;
use App\Models\User;
use Database\Seeders\RoleLimitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardByAliasAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleLimitsSeeder::class);
    }

    public function test_by_alias_groups_and_merges_same_display_name(): void
    {
        $user = User::factory()->admin()->create();

        $aliasA = TransactionAlias::factory()->for($user)->create([
            'display_name' => 'Mercado',
            'match_pattern' => 'EXTRA',
        ]);
        $aliasB = TransactionAlias::factory()->for($user)->create([
            'display_name' => 'Mercado',
            'match_pattern' => 'ASSAI',
            'match_type' => 'contains',
        ]);

        Transaction::factory()->manual()->for($user)->create([
            'occurred_on' => '2026-09-05',
            'type' => 'debit',
            'amount' => '100.00',
            'description' => 'Mercado',
            'raw_payload' => ['alias_id' => $aliasA->id, 'original_description' => 'EXTRA'],
        ]);
        Transaction::factory()->manual()->for($user)->create([
            'occurred_on' => '2026-09-06',
            'type' => 'debit',
            'amount' => '50.00',
            'description' => 'Mercado',
            'raw_payload' => ['alias_id' => $aliasB->id, 'original_description' => 'ASSAI'],
        ]);
        Transaction::factory()->manual()->for($user)->create([
            'occurred_on' => '2026-09-07',
            'type' => 'credit',
            'amount' => '200.00',
            'description' => 'Mercado',
            'raw_payload' => ['alias_id' => $aliasA->id, 'original_description' => 'EXTRA'],
        ]);
        Transaction::factory()->manual()->for($user)->create([
            'occurred_on' => '2026-09-08',
            'type' => 'debit',
            'amount' => '999.00',
            'description' => 'Sem apelido',
            'raw_payload' => ['original_description' => 'XYZ'],
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/analytics/dashboard?from=2026-09-01&to=2026-09-30');

        $response->assertOk();
        $byAlias = collect($response->json('data.by_alias'));
        $this->assertCount(2, $byAlias);

        $debit = $byAlias->firstWhere('type', 'debit');
        $credit = $byAlias->firstWhere('type', 'credit');
        $this->assertSame('Mercado', $debit['name']);
        $this->assertSame('150.00', $debit['total']);
        $this->assertSame(2, $debit['count']);
        $this->assertSame('Mercado', $credit['name']);
        $this->assertSame('200.00', $credit['total']);
        $this->assertSame(1, $credit['count']);
    }

    public function test_by_alias_resolves_active_rules_without_prior_apply(): void
    {
        $user = User::factory()->admin()->create();

        TransactionAlias::factory()->for($user)->create([
            'display_name' => 'SalárioSafado',
            'match_pattern' => 'Salário #8654',
            'match_type' => 'exact',
            'is_active' => true,
        ]);

        Transaction::factory()->manual()->for($user)->create([
            'occurred_on' => '2026-09-05',
            'type' => 'credit',
            'amount' => '5000.00',
            'description' => 'Salário #8654',
            'raw_payload' => ['original_description' => 'Salário #8654'],
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/analytics/dashboard?from=2026-09-01&to=2026-09-30');

        $response->assertOk();
        $byAlias = $response->json('data.by_alias');
        $this->assertCount(1, $byAlias);
        $this->assertSame('SalárioSafado', $byAlias[0]['name']);
        $this->assertSame('5000.00', $byAlias[0]['total']);
        $this->assertSame('credit', $byAlias[0]['type']);
    }
}

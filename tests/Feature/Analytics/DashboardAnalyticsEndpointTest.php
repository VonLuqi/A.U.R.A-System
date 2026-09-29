<?php

namespace Tests\Feature\Analytics;

use App\Models\Category;
use App\Models\StatementImport;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §5.5.3 — GET /api/analytics/dashboard response shape.
 */
class DashboardAnalyticsEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_filters_cards_series_and_by_category(): void
    {
        $user = User::factory()->create();
        $import = StatementImport::factory()->for($user)->create();
        $food = Category::factory()->create([
            'name' => 'Alimentação',
            'color' => '#DCCFFF',
        ]);

        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-09-05',
            'type' => 'credit',
            'amount' => '5000.00',
            'category_id' => $food->id,
            'description' => 'Salário',
        ]);
        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-09-10',
            'type' => 'debit',
            'amount' => '3200.50',
            'category_id' => $food->id,
            'description' => 'Mercado',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/analytics/dashboard?from=2026-09-01&to=2026-09-30&group_by=month');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'filters' => ['from', 'to', 'type', 'category_id', 'q', 'group_by', 'preset'],
                    'cards' => ['balance', 'total_income', 'total_expense', 'transactions_count'],
                    'series' => [
                        ['period', 'income', 'expense', 'balance'],
                    ],
                    'by_category' => [
                        ['category_id', 'name', 'color', 'total', 'count', 'type'],
                    ],
                    'by_alias' => [],
                    'goals' => [
                        'items',
                        'active_count',
                        'completed_count',
                        'paused_count',
                        'goals_used',
                        'goals_remaining',
                        'cards' => [
                            'average_progress_percent',
                            'nearest_deadline',
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.filters.from', '2026-09-01')
            ->assertJsonPath('data.filters.to', '2026-09-30')
            ->assertJsonPath('data.filters.group_by', 'month')
            ->assertJsonPath('data.cards.balance', '1799.50')
            ->assertJsonPath('data.cards.total_income', '5000.00')
            ->assertJsonPath('data.cards.total_expense', '3200.50')
            ->assertJsonPath('data.cards.transactions_count', 2)
            ->assertJsonPath('data.series.0.period', '2026-09')
            ->assertJsonPath('data.series.0.income', '5000.00')
            ->assertJsonPath('data.series.0.expense', '3200.50')
            ->assertJsonPath('data.series.0.balance', '1799.50')
            ->assertJsonPath('data.by_category.0.category_id', $food->id)
            ->assertJsonPath('data.by_category.0.name', 'Alimentação')
            ->assertJsonPath('data.by_category.0.color', '#DCCFFF')
            ->assertJsonPath('data.by_category.0.total', '3200.50')
            ->assertJsonPath('data.by_category.0.count', 1)
            ->assertJsonPath('data.by_category.0.type', 'debit');

        // Money must be JSON strings (not floats).
        $cards = $response->json('data.cards');
        $this->assertIsString($cards['balance']);
        $this->assertIsString($cards['total_income']);
        $this->assertIsString($cards['total_expense']);
        $this->assertIsString($response->json('data.series.0.income'));
        $this->assertIsString($response->json('data.by_category.0.total'));
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/analytics/dashboard')->assertUnauthorized();
    }

    public function test_applies_type_filter_to_cards_and_series(): void
    {
        $user = User::factory()->create();
        $import = StatementImport::factory()->for($user)->create();

        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-09-05',
            'type' => 'credit',
            'amount' => '100.00',
        ]);
        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-09-06',
            'type' => 'debit',
            'amount' => '40.00',
        ]);

        $this->actingAs($user)
            ->getJson('/api/analytics/dashboard?from=2026-09-01&to=2026-09-30&type=debit')
            ->assertOk()
            ->assertJsonPath('data.filters.type', 'debit')
            ->assertJsonPath('data.cards.total_income', '0.00')
            ->assertJsonPath('data.cards.total_expense', '40.00')
            ->assertJsonPath('data.cards.transactions_count', 1)
            ->assertJsonPath('data.by_category.0.type', 'debit');
    }
}

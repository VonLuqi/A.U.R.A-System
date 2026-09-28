<?php

namespace Tests\Feature\Transactions;

use App\Models\Category;
use App\Models\StatementImport;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §5.4.3 — GET /api/transactions response (data + meta pagination).
 */
class IndexTransactionsEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_transactions_with_data_and_meta_shape(): void
    {
        $user = User::factory()->create();
        $import = StatementImport::factory()->for($user)->create();
        $category = Category::factory()->create([
            'name' => 'Alimentação',
            'slug' => 'alimentacao',
            'color' => '#DCCFFF',
        ]);

        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-09-01',
            'description' => 'Supermercado Extra',
            'amount' => '89.90',
            'type' => 'debit',
            'category_id' => $category->id,
            'external_id' => null,
        ]);
        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-09-02',
            'description' => 'Pagamento recebido',
            'amount' => '100.00',
            'type' => 'credit',
            'category_id' => null,
        ]);

        $response = $this->actingAs($user)->getJson('/api/transactions?per_page=20');

        $response->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 1)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'occurred_on',
                        'description',
                        'amount',
                        'type',
                        'category',
                        'statement_import_id',
                        'external_id',
                    ],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);

        $first = collect($response->json('data'))->firstWhere('description', 'Supermercado Extra');
        $this->assertSame('2026-09-01', $first['occurred_on']);
        $this->assertSame('89.90', $first['amount']);
        $this->assertSame('debit', $first['type']);
        $this->assertSame($category->id, $first['category']['id']);
        $this->assertSame('Alimentação', $first['category']['name']);
        $this->assertSame('alimentacao', $first['category']['slug']);
        $this->assertSame('#DCCFFF', $first['category']['color']);
        $this->assertNull($first['external_id']);
    }

    public function test_paginates_and_filters_by_type(): void
    {
        $user = User::factory()->create();
        $import = StatementImport::factory()->for($user)->create();

        Transaction::factory()->count(3)->for($import, 'statementImport')->create(['type' => 'debit']);
        Transaction::factory()->count(2)->for($import, 'statementImport')->create(['type' => 'credit']);

        $response = $this->actingAs($user)->getJson('/api/transactions?type=credit&per_page=1');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'credit');
    }

    public function test_does_not_list_other_users_transactions(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Transaction::factory()->for(StatementImport::factory()->for($other), 'statementImport')->create();
        Transaction::factory()->for(StatementImport::factory()->for($user), 'statementImport')->create([
            'description' => 'Only mine',
        ]);

        $this->actingAs($user)
            ->getJson('/api/transactions')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.description', 'Only mine');
    }
}

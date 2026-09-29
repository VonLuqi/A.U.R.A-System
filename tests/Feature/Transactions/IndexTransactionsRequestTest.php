<?php

namespace Tests\Feature\Transactions;

use App\Models\Category;
use App\Models\StatementImport;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §5.4.1 / PLAN_EXPANSAO §6.1 — IndexTransactionsRequest query validation + date defaults.
 */
class IndexTransactionsRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/transactions')->assertUnauthorized();
    }

    public function test_defaults_to_current_month_when_dates_omitted(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-15 12:00:00', 'America/Sao_Paulo'));
        config(['app.timezone' => 'America/Sao_Paulo']);

        $user = User::factory()->create();
        $import = StatementImport::factory()->for($user)->create();

        Transaction::factory()->for($import, 'statementImport')->create([
            'user_id' => $user->id,
            'occurred_on' => '2026-09-10',
            'description' => 'In month',
        ]);
        Transaction::factory()->for($import, 'statementImport')->create([
            'user_id' => $user->id,
            'occurred_on' => '2026-08-10',
            'description' => 'Outside month',
        ]);

        try {
            $this->actingAs($user)
                ->getJson('/api/transactions')
                ->assertOk()
                ->assertJsonPath('meta.per_page', 20)
                ->assertJsonPath('meta.total', 1)
                ->assertJsonPath('data.0.description', 'In month');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_preset_current_month_filters_rows(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-15 12:00:00', 'America/Sao_Paulo'));
        config(['app.timezone' => 'America/Sao_Paulo']);

        $user = User::factory()->create();
        $import = StatementImport::factory()->for($user)->create();
        Transaction::factory()->for($import, 'statementImport')->create([
            'user_id' => $user->id,
            'occurred_on' => '2026-09-01',
        ]);

        try {
            $this->actingAs($user)
                ->getJson('/api/transactions?preset=current_month')
                ->assertOk()
                ->assertJsonPath('meta.total', 1);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_preset_custom_requires_from_and_to(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/transactions?preset=custom')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['from', 'to']);
    }

    public function test_accepts_valid_filter_combination(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $import = StatementImport::factory()->for($user)->create();

        $this->actingAs($user)
            ->getJson('/api/transactions?'.http_build_query([
                'from' => '2026-09-01',
                'to' => '2026-09-30',
                'type' => 'debit',
                'category_id' => $category->id,
                'q' => 'supermercado',
                'statement_import_id' => $import->id,
                'per_page' => 50,
                'sort' => 'amount',
                'direction' => 'asc',
                'page' => 2,
                'preset' => 'custom',
            ]))
            ->assertOk()
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_rejects_invalid_type_and_date_range(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/transactions?type=transfer&from=2026-09-30&to=2026-09-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'from', 'to']);
    }

    public function test_rejects_invalid_sort_per_page_and_missing_relations(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/transactions?sort=description&per_page=101&category_id=999999&statement_import_id=999999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort', 'per_page', 'category_id', 'statement_import_id']);
    }

    public function test_rejects_q_longer_than_120(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/transactions?q='.str_repeat('a', 121))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);
    }
}

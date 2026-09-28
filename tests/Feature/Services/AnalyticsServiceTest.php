<?php

namespace Tests\Feature\Services;

use App\Models\Category;
use App\Models\StatementImport;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * §5.5.2 — AnalyticsService (cards, series, by_category).
 */
class AnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AnalyticsService
    {
        return $this->app->make(AnalyticsService::class);
    }

    public function test_cards_compute_balance_income_expense_and_count(): void
    {
        $user = User::factory()->create();
        $import = StatementImport::factory()->for($user)->create();

        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-09-05',
            'type' => 'credit',
            'amount' => '5000.00',
            'description' => 'Salário',
        ]);
        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-09-10',
            'type' => 'debit',
            'amount' => '3200.50',
            'description' => 'Despesas',
        ]);
        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-08-01',
            'type' => 'debit',
            'amount' => '99.00',
            'description' => 'Fora do período',
        ]);

        $cards = $this->service()->cards($user, [
            'from' => '2026-09-01',
            'to' => '2026-09-30',
        ]);

        $this->assertSame('5000.00', $cards['total_income']);
        $this->assertSame('3200.50', $cards['total_expense']);
        $this->assertSame('1799.50', $cards['balance']);
        $this->assertSame(2, $cards['transactions_count']);
    }

    public function test_cards_ignore_other_users_transactions(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $ownerImport = StatementImport::factory()->for($owner)->create();
        $otherImport = StatementImport::factory()->for($other)->create();

        Transaction::factory()->for($ownerImport, 'statementImport')->create([
            'occurred_on' => '2026-09-01',
            'type' => 'credit',
            'amount' => '100.00',
        ]);
        Transaction::factory()->for($otherImport, 'statementImport')->create([
            'occurred_on' => '2026-09-01',
            'type' => 'credit',
            'amount' => '9999.00',
        ]);

        $cards = $this->service()->cards($owner, [
            'from' => '2026-09-01',
            'to' => '2026-09-30',
        ]);

        $this->assertSame('100.00', $cards['total_income']);
        $this->assertSame(1, $cards['transactions_count']);
    }

    public function test_series_groups_by_month_with_money_strings(): void
    {
        $user = User::factory()->create();
        $import = StatementImport::factory()->for($user)->create();

        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-09-05',
            'type' => 'credit',
            'amount' => '5000.00',
        ]);
        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-09-20',
            'type' => 'debit',
            'amount' => '3200.50',
        ]);
        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-08-15',
            'type' => 'credit',
            'amount' => '200.00',
        ]);

        $series = $this->service()->series($user, [
            'from' => '2026-08-01',
            'to' => '2026-09-30',
        ], 'month');

        $this->assertCount(2, $series);
        $this->assertSame('2026-08', $series[0]['period']);
        $this->assertSame('200.00', $series[0]['income']);
        $this->assertSame('0.00', $series[0]['expense']);
        $this->assertSame('200.00', $series[0]['balance']);
        $this->assertSame('2026-09', $series[1]['period']);
        $this->assertSame('5000.00', $series[1]['income']);
        $this->assertSame('3200.50', $series[1]['expense']);
        $this->assertSame('1799.50', $series[1]['balance']);
    }

    public function test_series_groups_by_day(): void
    {
        $user = User::factory()->create();
        $import = StatementImport::factory()->for($user)->create();

        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-09-05',
            'type' => 'debit',
            'amount' => '10.00',
        ]);
        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-09-06',
            'type' => 'debit',
            'amount' => '20.00',
        ]);

        $series = $this->service()->series($user, [
            'from' => '2026-09-01',
            'to' => '2026-09-30',
        ], 'day');

        $this->assertSame(['2026-09-05', '2026-09-06'], array_column($series, 'period'));
        $this->assertSame('10.00', $series[0]['expense']);
        $this->assertSame('20.00', $series[1]['expense']);
    }

    public function test_by_category_defaults_to_debit_and_includes_type(): void
    {
        $user = User::factory()->create();
        $import = StatementImport::factory()->for($user)->create();
        $food = Category::factory()->create([
            'name' => 'Alimentação',
            'color' => '#DCCFFF',
        ]);
        $transport = Category::factory()->create([
            'name' => 'Transporte',
            'color' => '#A8E6C3',
        ]);

        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-09-05',
            'type' => 'debit',
            'category_id' => $food->id,
            'amount' => '300.00',
        ]);
        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-09-06',
            'type' => 'debit',
            'category_id' => $food->id,
            'amount' => '150.00',
        ]);
        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-09-07',
            'type' => 'debit',
            'category_id' => $transport->id,
            'amount' => '50.00',
        ]);
        // Credit must not appear in default expense pie.
        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-09-08',
            'type' => 'credit',
            'category_id' => $food->id,
            'amount' => '999.00',
        ]);

        $byCategory = $this->service()->byCategory($user, [
            'from' => '2026-09-01',
            'to' => '2026-09-30',
        ]);

        $this->assertCount(2, $byCategory);
        $this->assertSame($food->id, $byCategory[0]['category_id']);
        $this->assertSame('Alimentação', $byCategory[0]['name']);
        $this->assertSame('#DCCFFF', $byCategory[0]['color']);
        $this->assertSame('450.00', $byCategory[0]['total']);
        $this->assertSame(2, $byCategory[0]['count']);
        $this->assertSame('debit', $byCategory[0]['type']);
        $this->assertSame($transport->id, $byCategory[1]['category_id']);
        $this->assertSame('50.00', $byCategory[1]['total']);
        $this->assertSame('debit', $byCategory[1]['type']);
    }

    public function test_by_category_respects_explicit_credit_type_filter(): void
    {
        $user = User::factory()->create();
        $import = StatementImport::factory()->for($user)->create();
        $income = Category::factory()->create(['name' => 'Receitas']);

        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-09-05',
            'type' => 'credit',
            'category_id' => $income->id,
            'amount' => '1000.00',
        ]);
        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-09-06',
            'type' => 'debit',
            'category_id' => $income->id,
            'amount' => '50.00',
        ]);

        $byCategory = $this->service()->byCategory($user, [
            'from' => '2026-09-01',
            'to' => '2026-09-30',
            'type' => 'credit',
        ]);

        $this->assertCount(1, $byCategory);
        $this->assertSame('1000.00', $byCategory[0]['total']);
        $this->assertSame('credit', $byCategory[0]['type']);
    }

    public function test_dashboard_combines_blocks_with_shared_filters(): void
    {
        $user = User::factory()->create();
        $import = StatementImport::factory()->for($user)->create();
        $food = Category::factory()->create(['name' => 'Alimentação', 'color' => '#DCCFFF']);

        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-09-05',
            'type' => 'credit',
            'amount' => '5000.00',
            'category_id' => $food->id,
        ]);
        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-09-10',
            'type' => 'debit',
            'amount' => '3200.50',
            'category_id' => $food->id,
        ]);

        $result = $this->service()->dashboard($user, [
            'from' => '2026-09-01',
            'to' => '2026-09-30',
            'group_by' => 'month',
        ]);

        $this->assertSame('1799.50', $result['cards']['balance']);
        $this->assertSame('2026-09', $result['series'][0]['period']);
        $this->assertSame('3200.50', $result['by_category'][0]['total']);
        $this->assertSame('debit', $result['by_category'][0]['type']);
    }

    public function test_each_block_uses_one_aggregated_query(): void
    {
        $user = User::factory()->create();
        $import = StatementImport::factory()->for($user)->create();
        $category = Category::factory()->create();

        Transaction::factory()->count(5)->for($import, 'statementImport')->create([
            'occurred_on' => '2026-09-15',
            'type' => 'debit',
            'category_id' => $category->id,
            'amount' => '10.00',
        ]);

        $filters = ['from' => '2026-09-01', 'to' => '2026-09-30'];

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->service()->cards($user, $filters);
        $cardQueries = count(DB::getQueryLog());
        DB::flushQueryLog();

        $this->service()->series($user, $filters, 'month');
        $seriesQueries = count(DB::getQueryLog());
        DB::flushQueryLog();

        $this->service()->byCategory($user, $filters);
        $categoryQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // whereHas may add a subquery inside the single SELECT — still one round-trip each.
        $this->assertLessThanOrEqual(2, $cardQueries);
        $this->assertLessThanOrEqual(2, $seriesQueries);
        $this->assertLessThanOrEqual(2, $categoryQueries);
    }
}

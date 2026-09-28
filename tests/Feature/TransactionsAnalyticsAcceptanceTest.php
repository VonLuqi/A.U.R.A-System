<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\StatementImport;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * §7.8 — Transactions / Analytics feature acceptance.
 */
class TransactionsAnalyticsAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_from_to_type_and_q_change_meta_total(): void
    {
        $user = User::factory()->create();
        $import = StatementImport::factory()->for($user)->create();
        $food = Category::factory()->create(['name' => 'Alimentação']);

        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-09-05',
            'type' => 'debit',
            'category_id' => $food->id,
            'description' => 'Supermercado Extra',
            'amount' => '89.90',
        ]);
        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-09-15',
            'type' => 'credit',
            'category_id' => $food->id,
            'description' => 'Pagamento recebido',
            'amount' => '100.00',
        ]);
        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-08-20',
            'type' => 'debit',
            'category_id' => $food->id,
            'description' => 'Supermercado Antigo',
            'amount' => '10.00',
        ]);
        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-09-08',
            'type' => 'debit',
            'category_id' => $food->id,
            'description' => 'Uber Trip',
            'amount' => '25.00',
        ]);

        $this->actingAs($user)
            ->getJson('/api/transactions')
            ->assertOk()
            ->assertJsonPath('meta.total', 4);

        $this->actingAs($user)
            ->getJson('/api/transactions?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);

        $this->actingAs($user)
            ->getJson('/api/transactions?from=2026-09-01&to=2026-09-30&type=debit')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->actingAs($user)
            ->getJson('/api/transactions?from=2026-09-01&to=2026-09-30&type=debit&q=Supermercado')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.description', 'Supermercado Extra');
    }

    public function test_dashboard_cards_match_manual_sql_sums(): void
    {
        $user = User::factory()->create();
        $import = StatementImport::factory()->for($user)->create();

        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-09-05',
            'type' => 'credit',
            'amount' => '5000.00',
        ]);
        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-09-10',
            'type' => 'debit',
            'amount' => '1200.25',
        ]);
        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-09-12',
            'type' => 'debit',
            'amount' => '300.25',
        ]);
        // Outside range — must not affect cards.
        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-08-01',
            'type' => 'credit',
            'amount' => '9999.00',
        ]);

        $manual = DB::table('transactions')
            ->join('statement_imports', 'statement_imports.id', '=', 'transactions.statement_import_id')
            ->where('statement_imports.user_id', $user->id)
            ->whereBetween('transactions.occurred_on', ['2026-09-01', '2026-09-30'])
            ->selectRaw("
                COUNT(*) as transactions_count,
                COALESCE(SUM(CASE WHEN transactions.type = 'credit' THEN transactions.amount ELSE 0 END), 0) as total_income,
                COALESCE(SUM(CASE WHEN transactions.type = 'debit' THEN transactions.amount ELSE 0 END), 0) as total_expense
            ")
            ->first();

        $expectedIncome = number_format((float) $manual->total_income, 2, '.', '');
        $expectedExpense = number_format((float) $manual->total_expense, 2, '.', '');
        $expectedBalance = number_format(
            (float) $manual->total_income - (float) $manual->total_expense,
            2,
            '.',
            ''
        );

        $this->assertSame('5000.00', $expectedIncome);
        $this->assertSame('1500.50', $expectedExpense);
        $this->assertSame('3499.50', $expectedBalance);
        $this->assertSame(3, (int) $manual->transactions_count);

        $this->actingAs($user)
            ->getJson('/api/analytics/dashboard?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonPath('data.cards.total_income', $expectedIncome)
            ->assertJsonPath('data.cards.total_expense', $expectedExpense)
            ->assertJsonPath('data.cards.balance', $expectedBalance)
            ->assertJsonPath('data.cards.transactions_count', (int) $manual->transactions_count);
    }

    public function test_user_cannot_see_other_users_imports_or_analytics(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $ownerImport = StatementImport::factory()->for($owner)->create([
            'original_filename' => 'owner.csv',
        ]);
        $otherImport = StatementImport::factory()->for($other)->create([
            'original_filename' => 'other.csv',
        ]);

        Transaction::factory()->for($ownerImport, 'statementImport')->create([
            'occurred_on' => '2026-09-05',
            'type' => 'credit',
            'amount' => '100.00',
            'description' => 'Owner credit',
        ]);
        Transaction::factory()->for($otherImport, 'statementImport')->create([
            'occurred_on' => '2026-09-05',
            'type' => 'credit',
            'amount' => '9999.00',
            'description' => 'Other credit',
        ]);

        $this->actingAs($owner)
            ->getJson('/api/statements')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.original_filename', 'owner.csv');

        $this->actingAs($owner)
            ->getJson('/api/statements/'.$otherImport->id)
            ->assertNotFound();

        $this->actingAs($owner)
            ->getJson('/api/transactions?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.description', 'Owner credit');

        $this->actingAs($owner)
            ->getJson('/api/analytics/dashboard?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonPath('data.cards.total_income', '100.00')
            ->assertJsonPath('data.cards.transactions_count', 1);
    }
}

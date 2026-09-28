<?php

namespace Tests\Feature\Services;

use App\Models\Category;
use App\Models\StatementImport;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * §5.4.2 — TransactionQueryService (scopes, eager load, ownership).
 */
class TransactionQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): TransactionQueryService
    {
        return $this->app->make(TransactionQueryService::class);
    }

    public function test_scopes_to_authenticated_user_ownership(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $ownerImport = StatementImport::factory()->for($owner)->create();
        $otherImport = StatementImport::factory()->for($other)->create();

        Transaction::factory()->for($ownerImport, 'statementImport')->create([
            'description' => 'Mine #1',
            'occurred_on' => '2026-09-10',
        ]);
        Transaction::factory()->for($otherImport, 'statementImport')->create([
            'description' => 'Other #1',
            'occurred_on' => '2026-09-10',
        ]);

        $ids = $this->service()->forUser($owner)->pluck('description')->all();

        $this->assertSame(['Mine #1'], $ids);
    }

    public function test_applies_date_type_category_q_and_import_filters_via_scopes(): void
    {
        $user = User::factory()->create();
        $import = StatementImport::factory()->for($user)->create();
        $otherImport = StatementImport::factory()->for($user)->create();
        $food = Category::factory()->create(['name' => 'Alimentação']);
        $transport = Category::factory()->create(['name' => 'Transporte']);

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
        Transaction::factory()->for($otherImport, 'statementImport')->create([
            'occurred_on' => '2026-09-08',
            'type' => 'debit',
            'category_id' => $transport->id,
            'description' => 'Uber Trip',
            'amount' => '25.00',
        ]);
        Transaction::factory()->for($import, 'statementImport')->create([
            'occurred_on' => '2026-08-01',
            'type' => 'debit',
            'category_id' => $food->id,
            'description' => 'Supermercado Antigo',
            'amount' => '10.00',
        ]);

        $rows = $this->service()->forUser($user, [
            'from' => '2026-09-01',
            'to' => '2026-09-30',
            'type' => 'debit',
            'category_id' => $food->id,
            'q' => 'Supermercado',
            'statement_import_id' => $import->id,
            'sort' => 'occurred_on',
            'direction' => 'asc',
        ])->get();

        $this->assertCount(1, $rows);
        $this->assertSame('Supermercado Extra', $rows->first()->description);
        $this->assertTrue($rows->first()->relationLoaded('category'));
        $this->assertSame($food->id, $rows->first()->category->id);
    }

    public function test_eager_loads_category_without_n_plus_one(): void
    {
        $user = User::factory()->create();
        $import = StatementImport::factory()->for($user)->create();
        $category = Category::factory()->create();

        Transaction::factory()->count(3)->for($import, 'statementImport')->create([
            'category_id' => $category->id,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $rows = $this->service()->forUser($user)->get();
        foreach ($rows as $row) {
            $this->assertNotNull($row->category?->name);
        }

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Expect ~2–3 queries (transactions + categories + maybe statement_imports exists), not 1+N.
        $this->assertLessThanOrEqual(5, count($queries));
        $this->assertCount(3, $rows);
    }

    public function test_credits_and_debits_scopes_and_sort_direction(): void
    {
        $user = User::factory()->create();
        $import = StatementImport::factory()->for($user)->create();

        Transaction::factory()->for($import, 'statementImport')->create([
            'type' => 'credit',
            'amount' => '50.00',
            'occurred_on' => '2026-09-01',
            'description' => 'Credit A',
        ]);
        Transaction::factory()->for($import, 'statementImport')->create([
            'type' => 'debit',
            'amount' => '10.00',
            'occurred_on' => '2026-09-02',
            'description' => 'Debit B',
        ]);
        Transaction::factory()->for($import, 'statementImport')->create([
            'type' => 'debit',
            'amount' => '30.00',
            'occurred_on' => '2026-09-03',
            'description' => 'Debit C',
        ]);

        $debits = $this->service()->forUser($user, [
            'type' => 'debit',
            'sort' => 'amount',
            'direction' => 'desc',
        ])->pluck('description')->all();

        $this->assertSame(['Debit C', 'Debit B'], $debits);
    }
}

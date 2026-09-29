<?php

namespace Tests\Unit\Services;

use App\Enums\GoalKind;
use App\Enums\GoalStatus;
use App\Models\Category;
use App\Models\Goal;
use App\Models\Transaction;
use App\Models\User;
use App\Services\GoalProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PLAN_EXPANSAO §3.2 / §7.1 — linked goal progress from transactions.
 */
class GoalProgressServiceTest extends TestCase
{
    use RefreshDatabase;

    private GoalProgressService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GoalProgressService::class);
    }

    public function test_savings_goal_sums_matching_credits_and_completes(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $goal = Goal::factory()->savings()->for($user)->create([
            'category_id' => $category->id,
            'linked_description_pattern' => '%aporte%',
            'target_amount' => '100.00',
            'current_amount' => '0.00',
            'status' => GoalStatus::Active,
            'created_at' => now()->subMonth(),
        ]);

        Transaction::factory()->manual()->for($user)->create([
            'category_id' => $category->id,
            'type' => 'credit',
            'amount' => '40.00',
            'description' => 'Meu aporte mensal',
            'occurred_on' => now()->toDateString(),
        ]);

        Transaction::factory()->manual()->for($user)->create([
            'category_id' => $category->id,
            'type' => 'credit',
            'amount' => '70.00',
            'description' => 'aporte extra',
            'occurred_on' => now()->toDateString(),
        ]);

        // Wrong type — ignored
        Transaction::factory()->manual()->for($user)->create([
            'category_id' => $category->id,
            'type' => 'debit',
            'amount' => '999.00',
            'description' => 'aporte falso',
            'occurred_on' => now()->toDateString(),
        ]);

        $this->service->recalculateLinked($goal->fresh());

        $goal->refresh();
        $this->assertSame('110.00', (string) $goal->current_amount);
        $this->assertSame(GoalStatus::Completed, $goal->status);
    }

    public function test_debt_payoff_sums_debits_and_reopens_after_delete(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $goal = Goal::factory()->debtPayoff()->for($user)->create([
            'category_id' => $category->id,
            'linked_description_pattern' => null,
            'target_amount' => '50.00',
            'current_amount' => '0.00',
            'status' => GoalStatus::Active,
            'created_at' => now()->subMonth(),
        ]);

        $tx = Transaction::factory()->manual()->for($user)->create([
            'category_id' => $category->id,
            'type' => 'debit',
            'amount' => '50.00',
            'description' => 'Pagamento fatura',
            'occurred_on' => now()->toDateString(),
        ]);

        $this->service->touchFromTransaction($tx);
        $this->assertSame(GoalStatus::Completed, $goal->fresh()->status);
        $this->assertSame('50.00', (string) $goal->fresh()->current_amount);

        $tx->delete();
        $this->service->recalculateLinkedForUser((int) $user->id);

        $goal->refresh();
        $this->assertSame(GoalStatus::Active, $goal->status);
        $this->assertSame('0.00', (string) $goal->current_amount);
    }

    public function test_unlinked_goal_is_not_changed(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->for($user)->create([
            'category_id' => null,
            'linked_description_pattern' => null,
            'current_amount' => '10.00',
            'kind' => GoalKind::Savings,
        ]);

        Transaction::factory()->manual()->for($user)->create([
            'type' => 'credit',
            'amount' => '500.00',
            'occurred_on' => now()->toDateString(),
        ]);

        $this->service->recalculateLinkedForUser((int) $user->id);

        $this->assertSame('10.00', (string) $goal->fresh()->current_amount);
    }

    public function test_regex_description_pattern_matches(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->savings()->for($user)->create([
            'category_id' => null,
            'linked_description_pattern' => '/^PIX\\s+POUPANCA/i',
            'target_amount' => '200.00',
            'current_amount' => '0.00',
            'created_at' => now()->subMonth(),
        ]);

        Transaction::factory()->manual()->for($user)->create([
            'type' => 'credit',
            'amount' => '80.00',
            'description' => 'PIX POUPANCA setembro',
            'occurred_on' => now()->toDateString(),
        ]);

        Transaction::factory()->manual()->for($user)->create([
            'type' => 'credit',
            'amount' => '80.00',
            'description' => 'Outro credito',
            'occurred_on' => now()->toDateString(),
        ]);

        $this->service->recalculateLinked($goal->fresh());

        $this->assertSame('80.00', (string) $goal->fresh()->current_amount);
    }
}

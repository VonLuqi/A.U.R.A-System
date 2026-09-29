<?php

namespace Tests\Unit\Services;

use App\Enums\GoalKind;
use App\Enums\GoalStatus;
use App\Exceptions\UsageLimitExceededException;
use App\Models\Category;
use App\Models\Goal;
use App\Models\Transaction;
use App\Models\User;
use App\Services\GoalService;
use Database\Seeders\RoleLimitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * PLAN_EXPANSAO §7.1 — GoalService CRUD + recalculate (manual / linked).
 */
class GoalServiceTest extends TestCase
{
    use RefreshDatabase;

    private GoalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleLimitsSeeder::class);
        $this->service = app(GoalService::class);
    }

    public function test_create_manual_goal_and_list_for_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $goal = $this->service->create($user, [
            'name' => 'Reserva',
            'kind' => GoalKind::Savings->value,
            'target_amount' => '1000.00',
            'current_amount' => '250.00',
            'progress_mode' => GoalService::PROGRESS_MANUAL,
        ]);

        $this->assertSame($user->id, $goal->user_id);
        $this->assertSame('250.00', (string) $goal->current_amount);
        $this->assertFalse($goal->isLinked());
        $this->assertSame(GoalService::PROGRESS_MANUAL, $this->service->progressMode($goal));
        $this->assertSame(GoalStatus::Active, $goal->status);

        Goal::factory()->for($other)->create(['name' => 'Outra']);

        $list = $this->service->listForUser($user);
        $this->assertCount(1, $list);
        $this->assertSame('Reserva', $list->first()->name);
    }

    public function test_create_linked_goal_recalculates_from_transactions(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        Transaction::factory()->manual()->for($user)->create([
            'category_id' => $category->id,
            'type' => 'credit',
            'amount' => '40.00',
            'description' => 'aporte setembro',
            'occurred_on' => now()->toDateString(),
        ]);

        $goal = $this->service->create($user, [
            'name' => 'Poupança',
            'kind' => GoalKind::Savings->value,
            'target_amount' => '100.00',
            'category_id' => $category->id,
            'linked_description_pattern' => '%aporte%',
            'progress_mode' => GoalService::PROGRESS_LINKED,
        ]);

        $this->assertTrue($goal->isLinked());
        $this->assertSame('40.00', (string) $goal->current_amount);
        $this->assertSame(GoalStatus::Active, $goal->status);
    }

    public function test_manual_recalculate_completes_when_amount_meets_target(): void
    {
        $user = User::factory()->create();
        $goal = $this->service->create($user, [
            'name' => 'Meta manual',
            'kind' => GoalKind::Savings->value,
            'target_amount' => '100.00',
            'current_amount' => '50.00',
            'progress_mode' => GoalService::PROGRESS_MANUAL,
        ]);

        $updated = $this->service->update($user, $goal, [
            'current_amount' => '100.00',
        ]);

        $this->assertSame(GoalStatus::Completed, $updated->status);
        $this->assertSame('100.00', (string) $updated->current_amount);
    }

    public function test_update_to_linked_and_delete(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $goal = $this->service->create($user, [
            'name' => 'Dívida',
            'kind' => GoalKind::DebtPayoff->value,
            'target_amount' => '80.00',
            'current_amount' => '10.00',
            'progress_mode' => GoalService::PROGRESS_MANUAL,
        ]);

        Transaction::factory()->manual()->for($user)->create([
            'category_id' => $category->id,
            'type' => 'debit',
            'amount' => '80.00',
            'description' => 'Pagamento',
            'occurred_on' => now()->toDateString(),
        ]);

        $linked = $this->service->update($user, $goal, [
            'progress_mode' => GoalService::PROGRESS_LINKED,
            'category_id' => $category->id,
            'linked_description_pattern' => null,
        ]);

        $this->assertSame(GoalService::PROGRESS_LINKED, $this->service->progressMode($linked));
        $this->assertSame('80.00', (string) $linked->current_amount);
        $this->assertSame(GoalStatus::Completed, $linked->status);

        $this->service->delete($linked);
        $this->assertDatabaseMissing('goals', ['id' => $linked->id]);
    }

    public function test_linked_without_filters_fails_validation(): void
    {
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);

        $this->service->create($user, [
            'name' => 'Inválida',
            'kind' => GoalKind::Savings->value,
            'target_amount' => '50.00',
            'progress_mode' => GoalService::PROGRESS_LINKED,
        ]);
    }

    public function test_visitor_goal_quota_is_enforced(): void
    {
        $visitor = User::factory()->visitor()->create();
        $limit = (int) app(\App\Services\UsageLimitService::class)->roleLimitsBag($visitor)['max_goals'];
        $this->assertGreaterThan(0, $limit);

        for ($i = 0; $i < $limit; $i++) {
            $this->service->create($visitor, [
                'name' => "Meta {$i}",
                'kind' => GoalKind::Savings->value,
                'target_amount' => '10.00',
                'current_amount' => '0.00',
                'progress_mode' => GoalService::PROGRESS_MANUAL,
            ]);
        }

        $this->expectException(UsageLimitExceededException::class);

        $this->service->create($visitor, [
            'name' => 'Uma a mais',
            'kind' => GoalKind::Savings->value,
            'target_amount' => '10.00',
            'progress_mode' => GoalService::PROGRESS_MANUAL,
        ]);
    }

    public function test_recalculate_manual_reopens_when_amount_drops(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->for($user)->create([
            'target_amount' => '100.00',
            'current_amount' => '100.00',
            'status' => GoalStatus::Completed,
            'category_id' => null,
            'linked_description_pattern' => null,
        ]);

        $goal->current_amount = '40.00';
        $goal->save();

        $recalculated = $this->service->recalculate($goal->fresh());

        $this->assertSame(GoalStatus::Active, $recalculated->status);
        $this->assertSame('40.00', (string) $recalculated->current_amount);
    }
}

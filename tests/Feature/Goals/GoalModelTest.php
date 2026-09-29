<?php

namespace Tests\Feature\Goals;

use App\Enums\GoalKind;
use App\Enums\GoalStatus;
use App\Enums\UserRole;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_goal_for_user(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->savings()->for($user)->create([
            'name' => 'Reserva',
            'target_amount' => '1000.00',
            'current_amount' => '250.00',
        ]);

        $this->assertSame($user->id, $goal->user_id);
        $this->assertSame(GoalKind::Savings, $goal->kind);
        $this->assertSame(GoalStatus::Active, $goal->status);
        $this->assertSame(25.0, $goal->progressPercent());
        $this->assertSame('750.00', $goal->remainingAmount());
        $this->assertTrue($user->goals()->whereKey($goal->id)->exists());
    }

    public function test_scope_for_user_isolates_goals(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $owned = Goal::factory()->for($owner)->create();
        Goal::factory()->for($other)->create();

        $this->assertSame([$owned->id], Goal::query()->forUser($owner)->pluck('id')->all());
    }

    public function test_linked_state_sets_category_and_pattern(): void
    {
        $goal = Goal::factory()->linked(pattern: 'pix reserva')->create();

        $this->assertTrue($goal->isLinked());
        $this->assertNotNull($goal->category_id);
        $this->assertSame('pix reserva', $goal->linked_description_pattern);
    }

    public function test_policy_allows_owner_and_denies_other(): void
    {
        $owner = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $goal = Goal::factory()->for($owner)->create();

        $this->assertTrue($owner->can('view', $goal));
        $this->assertTrue($owner->can('update', $goal));
        $this->assertTrue($owner->can('delete', $goal));
        $this->assertTrue($owner->can('create', Goal::class));
        $this->assertFalse($other->can('view', $goal));
    }

    public function test_policy_denies_role_without_goals_manage_ability(): void
    {
        config([
            'aura.abilities' => [
                'goals.manage' => [UserRole::Admin->value],
            ],
        ]);

        $visitor = User::factory()->visitor()->create();

        $this->assertFalse($visitor->can('create', Goal::class));
        $this->assertFalse($visitor->can('viewAny', Goal::class));
    }

    public function test_completed_factory_state_fills_current_to_target(): void
    {
        $goal = Goal::factory()->completed()->create([
            'target_amount' => '2000.00',
        ]);

        $this->assertSame(GoalStatus::Completed, $goal->status);
        $this->assertSame('2000.00', (string) $goal->current_amount);
        $this->assertSame(100.0, $goal->progressPercent());
    }
}

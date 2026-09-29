<?php

namespace Tests\Feature\Goals;

use App\Enums\GoalKind;
use App\Enums\GoalStatus;
use App\Models\Category;
use App\Models\Goal;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleLimitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PLAN_EXPANSAO §7.2 — Goals HTTP API.
 */
class GoalsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleLimitsSeeder::class);
    }

    public function test_goals_require_authentication(): void
    {
        $this->getJson('/api/goals')->assertUnauthorized();
        $this->postJson('/api/goals')->assertUnauthorized();
    }

    public function test_crud_and_recalculate_flow(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $create = $this->actingAs($user)->postJson('/api/goals', [
            'name' => 'Reserva',
            'kind' => GoalKind::Savings->value,
            'target_amount' => '100.00',
            'current_amount' => '25.00',
            'progress_mode' => 'manual',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Reserva')
            ->assertJsonPath('data.progress_mode', 'manual')
            ->assertJsonPath('data.progress_percent', 25)
            ->assertJsonPath('data.remaining_amount', '75.00');

        $id = (int) $create->json('data.id');

        $this->actingAs($user)
            ->getJson('/api/goals')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonStructure(['meta' => ['goals_used', 'goals_remaining']]);

        $this->actingAs($user)
            ->patchJson('/api/goals/'.$id, [
                'current_amount' => '100.00',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', GoalStatus::Completed->value)
            ->assertJsonPath('data.progress_percent', 100);

        Transaction::factory()->manual()->for($user)->create([
            'category_id' => $category->id,
            'type' => 'credit',
            'amount' => '60.00',
            'description' => 'aporte',
            'occurred_on' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->patchJson('/api/goals/'.$id, [
                'progress_mode' => 'linked',
                'category_id' => $category->id,
                'linked_description_pattern' => '%aporte%',
                'status' => GoalStatus::Active->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.progress_mode', 'linked')
            ->assertJsonPath('data.current_amount', '60.00');

        $this->actingAs($user)
            ->postJson('/api/goals/'.$id.'/recalculate')
            ->assertOk()
            ->assertJsonPath('data.current_amount', '60.00');

        $this->actingAs($user)
            ->deleteJson('/api/goals/'.$id)
            ->assertOk()
            ->assertJsonPath('message', 'Meta removida.');

        $this->assertDatabaseMissing('goals', ['id' => $id]);
    }

    public function test_owner_isolation_and_quota(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $goal = Goal::factory()->for($owner)->create();

        $this->actingAs($other)
            ->getJson('/api/goals/'.$goal->id)
            ->assertNotFound();

        $visitor = User::factory()->visitor()->create();
        $limit = (int) config('aura.limits.visitor.max_goals');

        for ($i = 0; $i < $limit; $i++) {
            $this->actingAs($visitor)
                ->postJson('/api/goals', [
                    'name' => "V{$i}",
                    'kind' => 'savings',
                    'target_amount' => '10',
                    'progress_mode' => 'manual',
                ])
                ->assertCreated();
        }

        $this->actingAs($visitor)
            ->postJson('/api/goals', [
                'name' => 'Over',
                'kind' => 'savings',
                'target_amount' => '10',
                'progress_mode' => 'manual',
            ])
            ->assertStatus(429)
            ->assertJsonPath('error_code', 'usage_limit_exceeded')
            ->assertJsonPath('metric', 'goals');
    }

    public function test_dashboard_includes_goals_summary(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-15 12:00:00', 'America/Sao_Paulo'));
        config(['app.timezone' => 'America/Sao_Paulo']);

        $user = User::factory()->create();
        Goal::factory()->for($user)->create([
            'name' => 'Viagem',
            'target_amount' => '1000.00',
            'current_amount' => '400.00',
            'status' => GoalStatus::Active,
            'deadline_on' => '2026-10-15',
        ]);
        Goal::factory()->for($user)->create([
            'name' => 'Reserva',
            'target_amount' => '100.00',
            'current_amount' => '60.00',
            'status' => GoalStatus::Active,
            'deadline_on' => '2026-12-01',
        ]);

        try {
            $this->actingAs($user)
                ->getJson('/api/analytics/dashboard')
                ->assertOk()
                ->assertJsonPath('data.goals.items.0.name', 'Viagem')
                ->assertJsonPath('data.goals.items.0.progress_percent', 40)
                ->assertJsonPath('data.goals.items.0.remaining_amount', '600.00')
                ->assertJsonPath('data.goals.active_count', 2)
                ->assertJsonPath('data.goals.cards.average_progress_percent', 50)
                ->assertJsonPath('data.goals.cards.nearest_deadline.name', 'Viagem')
                ->assertJsonPath('data.goals.cards.nearest_deadline.deadline_on', '2026-10-15')
                ->assertJsonStructure([
                    'data' => [
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
                ]);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_validation_rejects_linked_without_filters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/goals', [
                'name' => 'Bad',
                'kind' => 'savings',
                'target_amount' => '50',
                'progress_mode' => 'linked',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['progress_mode']);
    }
}

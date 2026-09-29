<?php

namespace Tests\Unit\Services;

use App\Enums\GoalStatus;
use App\Models\Goal;
use App\Models\User;
use App\Services\DashboardGoalsAggregator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PLAN_EXPANSAO §7.3 — goals analytics cards.
 */
class DashboardGoalsAggregatorTest extends TestCase
{
    use RefreshDatabase;

    private DashboardGoalsAggregator $aggregator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aggregator = app(DashboardGoalsAggregator::class);
    }

    public function test_average_progress_and_nearest_deadline_cards(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-15 12:00:00', 'America/Sao_Paulo'));
        config(['app.timezone' => 'America/Sao_Paulo']);

        $user = User::factory()->create();
        $other = User::factory()->create();

        Goal::factory()->for($user)->create([
            'name' => 'Meta A',
            'target_amount' => '100.00',
            'current_amount' => '25.00',
            'status' => GoalStatus::Active,
            'deadline_on' => '2026-10-01',
        ]);
        Goal::factory()->for($user)->create([
            'name' => 'Meta B',
            'target_amount' => '100.00',
            'current_amount' => '75.00',
            'status' => GoalStatus::Active,
            'deadline_on' => '2026-12-01',
        ]);
        Goal::factory()->for($user)->create([
            'name' => 'Concluída',
            'target_amount' => '50.00',
            'current_amount' => '50.00',
            'status' => GoalStatus::Completed,
            'deadline_on' => '2026-09-20',
        ]);
        Goal::factory()->for($other)->create([
            'name' => 'Outro user',
            'deadline_on' => '2026-09-16',
            'status' => GoalStatus::Active,
        ]);

        try {
            $summary = $this->aggregator->forUser($user);

            $this->assertSame(2, $summary['active_count']);
            $this->assertSame(1, $summary['completed_count']);
            $this->assertSame(50.0, $summary['cards']['average_progress_percent']);
            $this->assertSame('Meta A', $summary['cards']['nearest_deadline']['name']);
            $this->assertSame('2026-10-01', $summary['cards']['nearest_deadline']['deadline_on']);
            $this->assertSame(16, $summary['cards']['nearest_deadline']['days_remaining']);
            $this->assertFalse($summary['cards']['nearest_deadline']['is_overdue']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_overdue_deadline_when_no_upcoming(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-15 12:00:00', 'America/Sao_Paulo'));
        config(['app.timezone' => 'America/Sao_Paulo']);

        $user = User::factory()->create();
        Goal::factory()->for($user)->create([
            'name' => 'Atrasada',
            'target_amount' => '100.00',
            'current_amount' => '10.00',
            'status' => GoalStatus::Active,
            'deadline_on' => '2026-09-01',
        ]);

        try {
            $card = $this->aggregator->forUser($user)['cards']['nearest_deadline'];

            $this->assertSame('Atrasada', $card['name']);
            $this->assertTrue($card['is_overdue']);
            $this->assertLessThan(0, $card['days_remaining']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_empty_goals_yield_null_cards(): void
    {
        $user = User::factory()->create();
        $summary = $this->aggregator->forUser($user);

        $this->assertSame([], $summary['items']);
        $this->assertNull($summary['cards']['average_progress_percent']);
        $this->assertNull($summary['cards']['nearest_deadline']);
    }
}

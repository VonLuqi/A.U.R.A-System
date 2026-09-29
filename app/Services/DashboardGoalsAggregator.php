<?php

namespace App\Services;

use App\Enums\GoalStatus;
use App\Http\Resources\GoalResource;
use App\Models\Goal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Goals block for analytics dashboard (PLAN_EXPANSAO §7.2 / §7.3).
 *
 * Single query (+ quota counts) — no N+1. Adds suggested cards:
 * - average_progress_percent (metas active)
 * - nearest_deadline (próximo prazo entre active|paused com deadline)
 */
final class DashboardGoalsAggregator
{
    public function __construct(
        private readonly UsageLimitService $usageLimits,
    ) {}

    /**
     * @return array{
     *     items: list<array<string, mixed>>,
     *     active_count: int,
     *     completed_count: int,
     *     paused_count: int,
     *     goals_used: int,
     *     goals_remaining: int|null,
     *     cards: array{
     *         average_progress_percent: float|null,
     *         nearest_deadline: array<string, mixed>|null
     *     }
     * }
     */
    public function forUser(User $user): array
    {
        /** @var Collection<int, Goal> $goals */
        $goals = Goal::query()
            ->forUser($user)
            ->with('category')
            ->whereIn('status', [GoalStatus::Active, GoalStatus::Completed, GoalStatus::Paused])
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'paused' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END")
            ->orderBy('deadline_on')
            ->orderBy('id')
            ->get();

        $items = [];
        $active = 0;
        $completed = 0;
        $paused = 0;
        $activeProgressSum = 0.0;

        foreach ($goals as $goal) {
            $status = $goal->status instanceof GoalStatus
                ? $goal->status
                : GoalStatus::tryFrom((string) $goal->status);

            if ($status === GoalStatus::Active) {
                $active++;
                $activeProgressSum += $goal->progressPercent();
            } elseif ($status === GoalStatus::Completed) {
                $completed++;
            } elseif ($status === GoalStatus::Paused) {
                $paused++;
            }

            $items[] = (new GoalResource($goal))->toSummaryArray();
        }

        return [
            'items' => $items,
            'active_count' => $active,
            'completed_count' => $completed,
            'paused_count' => $paused,
            'goals_used' => $this->usageLimits->goalsUsed($user),
            'goals_remaining' => $this->usageLimits->goalsRemaining($user),
            'cards' => [
                'average_progress_percent' => $active > 0
                    ? round($activeProgressSum / $active, 2)
                    : null,
                'nearest_deadline' => $this->nearestDeadlineCard($goals),
            ],
        ];
    }

    /**
     * @param  Collection<int, Goal>  $goals
     * @return array{
     *     id: int,
     *     name: string,
     *     status: string,
     *     deadline_on: string,
     *     days_remaining: int,
     *     progress_percent: float,
     *     remaining_amount: string,
     *     is_overdue: bool
     * }|null
     */
    private function nearestDeadlineCard(Collection $goals): ?array
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();

        $withDeadline = $goals
            ->filter(function (Goal $goal) use ($today): bool {
                $status = $goal->status instanceof GoalStatus
                    ? $goal->status
                    : GoalStatus::tryFrom((string) $goal->status);

                if (! in_array($status, [GoalStatus::Active, GoalStatus::Paused], true)) {
                    return false;
                }

                return $goal->deadline_on !== null;
            })
            ->sortBy(fn (Goal $goal): string => $goal->deadline_on->format('Y-m-d'))
            ->values();

        if ($withDeadline->isEmpty()) {
            return null;
        }

        // Prefer the soonest upcoming (≥ today); otherwise the most recent overdue.
        $upcoming = $withDeadline->first(
            fn (Goal $goal): bool => $goal->deadline_on->greaterThanOrEqualTo($today)
        );
        $goal = $upcoming ?? $withDeadline->last();

        $deadline = CarbonImmutable::instance($goal->deadline_on)->startOfDay();
        $days = (int) $today->diffInDays($deadline, false);
        $status = $goal->status instanceof GoalStatus
            ? $goal->status->value
            : (string) $goal->status;

        return [
            'id' => (int) $goal->id,
            'name' => (string) $goal->name,
            'status' => $status,
            'deadline_on' => $deadline->format('Y-m-d'),
            'days_remaining' => $days,
            'progress_percent' => $goal->progressPercent(),
            'remaining_amount' => $goal->remainingAmount(),
            'is_overdue' => $days < 0,
        ];
    }
}

<?php

namespace App\Services;

use App\Enums\GoalKind;
use App\Enums\GoalStatus;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Goals CRUD + recalculate (PLAN_EXPANSAO §7.1).
 *
 * Progress modes (no dedicated column — derived from link fields):
 * - **manual** — no category_id / linked_description_pattern; user sets current_amount
 * - **linked** — category_id and/or pattern; current_amount derived from matching txs
 *
 * Quota: stock `max_goals` via UsageLimitService::assertCanCreateGoal (0 = unlimited).
 * Dashboard summary cards: DashboardGoalsAggregator (§7.3).
 */
final class GoalService
{
    public const PROGRESS_MANUAL = 'manual';

    public const PROGRESS_LINKED = 'linked';

    public function __construct(
        private readonly GoalProgressService $progress,
        private readonly UsageLimitService $usageLimits,
        private readonly DashboardGoalsAggregator $goalsAggregator,
    ) {}

    /**
     * @param  array{
     *     status?: string|null,
     *     kind?: string|null,
     *     q?: string|null
     * }  $filters
     * @return Builder<Goal>
     */
    public function queryForUser(User $user, array $filters = []): Builder
    {
        $query = Goal::query()
            ->forUser($user)
            ->with('category')
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'completed' THEN 1 WHEN 'paused' THEN 2 ELSE 3 END")
            ->orderBy('deadline_on')
            ->orderBy('id');

        $status = $filters['status'] ?? null;
        if (is_string($status) && $status !== '' && in_array($status, GoalStatus::values(), true)) {
            $query->where('status', $status);
        }

        $kind = $filters['kind'] ?? null;
        if (is_string($kind) && $kind !== '' && in_array($kind, GoalKind::values(), true)) {
            $query->where('kind', $kind);
        }

        $q = $filters['q'] ?? null;
        if (is_string($q) && $q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where('name', 'like', $like);
        }

        return $query;
    }

    /**
     * @param  array{status?: string|null, kind?: string|null, q?: string|null}  $filters
     * @return Collection<int, Goal>
     */
    public function listForUser(User $user, array $filters = []): Collection
    {
        return $this->queryForUser($user, $filters)->get();
    }

    /**
     * @param  array{
     *     name: string,
     *     kind: string,
     *     target_amount: numeric,
     *     current_amount?: numeric|null,
     *     currency?: string|null,
     *     deadline_on?: string|null,
     *     category_id?: int|null,
     *     linked_description_pattern?: string|null,
     *     status?: string|null,
     *     metadata?: array<string, mixed>|null,
     *     progress_mode?: string|null
     * }  $data
     */
    public function create(User $user, array $data): Goal
    {
        $this->usageLimits->assertCanCreateGoal($user);

        $attrs = $this->normalizeAttributes($data, creating: true);

        $goal = DB::transaction(function () use ($user, $attrs): Goal {
            return Goal::query()->create([
                ...$attrs,
                'user_id' => $user->id,
            ]);
        });

        return $this->recalculate($goal);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, Goal $goal, array $data): Goal
    {
        if ((int) $goal->user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'goal' => ['Meta não pertence ao usuário autenticado.'],
            ]);
        }

        $attrs = $this->normalizeAttributes($data, creating: false, existing: $goal);

        $goal->fill($attrs);
        $goal->save();

        return $this->recalculate($goal->fresh() ?? $goal);
    }

    public function delete(Goal $goal): void
    {
        $goal->delete();
    }

    /**
     * Linked → sum matching transactions; manual → keep current_amount, sync status.
     */
    public function recalculate(Goal $goal): Goal
    {
        if ($goal->isLinked()) {
            $this->progress->recalculateLinked($goal);
        } else {
            $this->progress->syncCompletionStatus($goal);
            $goal->save();
        }

        return $goal->fresh(['category']) ?? $goal->load('category');
    }

    public function progressMode(Goal $goal): string
    {
        return $goal->isLinked() ? self::PROGRESS_LINKED : self::PROGRESS_MANUAL;
    }

    /**
     * Compact goals block for GET /api/analytics/dashboard (PLAN_EXPANSAO §7.2 / §7.3).
     * Delegates to DashboardGoalsAggregator (single query, cards included).
     *
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
    public function dashboardSummary(User $user): array
    {
        return $this->goalsAggregator->forUser($user);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeAttributes(array $data, bool $creating, ?Goal $existing = null): array
    {
        $mode = $this->resolveProgressMode($data, $existing);

        $categoryId = array_key_exists('category_id', $data)
            ? $data['category_id']
            : ($creating ? null : $existing?->category_id);
        $pattern = array_key_exists('linked_description_pattern', $data)
            ? $data['linked_description_pattern']
            : ($creating ? null : $existing?->linked_description_pattern);

        if ($mode === self::PROGRESS_MANUAL) {
            $categoryId = null;
            $pattern = null;
        } else {
            $categoryId = $categoryId !== null && $categoryId !== '' ? (int) $categoryId : null;
            $pattern = is_string($pattern) ? trim($pattern) : null;
            $pattern = $pattern === '' ? null : $pattern;

            if ($categoryId === null && $pattern === null) {
                throw ValidationException::withMessages([
                    'progress_mode' => ['Modo linked exige category_id e/ou linked_description_pattern.'],
                ]);
            }
        }

        $attrs = [];

        if ($creating || array_key_exists('name', $data)) {
            $attrs['name'] = (string) ($data['name'] ?? $existing?->name);
        }
        if ($creating || array_key_exists('kind', $data)) {
            $attrs['kind'] = (string) ($data['kind'] ?? $existing?->kind?->value ?? GoalKind::Savings->value);
        }
        if ($creating || array_key_exists('target_amount', $data)) {
            $attrs['target_amount'] = $this->money($data['target_amount'] ?? $existing?->target_amount ?? 0);
        }

        if ($mode === self::PROGRESS_MANUAL) {
            if ($creating || array_key_exists('current_amount', $data)) {
                $attrs['current_amount'] = $this->money($data['current_amount'] ?? $existing?->current_amount ?? 0);
            }
        } elseif ($creating) {
            // Linked starts at 0 until recalculate fills from txs.
            $attrs['current_amount'] = '0.00';
        }

        if ($creating || array_key_exists('currency', $data)) {
            $attrs['currency'] = strtoupper((string) ($data['currency'] ?? $existing?->currency ?? 'BRL'));
        }
        if ($creating || array_key_exists('deadline_on', $data)) {
            $deadline = $data['deadline_on'] ?? ($creating ? null : $existing?->deadline_on);
            $attrs['deadline_on'] = $deadline instanceof \DateTimeInterface
                ? $deadline->format('Y-m-d')
                : ($deadline !== null && $deadline !== '' ? (string) $deadline : null);
        }
        if ($creating || array_key_exists('status', $data)) {
            $attrs['status'] = (string) ($data['status'] ?? $existing?->status?->value ?? GoalStatus::Active->value);
        }

        // Always write link fields when mode is known (clear on manual).
        if ($creating
            || array_key_exists('progress_mode', $data)
            || array_key_exists('category_id', $data)
            || array_key_exists('linked_description_pattern', $data)
        ) {
            $attrs['category_id'] = $categoryId;
            $attrs['linked_description_pattern'] = $pattern;
        }

        if (array_key_exists('metadata', $data)) {
            $attrs['metadata'] = is_array($data['metadata']) ? $data['metadata'] : null;
        }

        $metadata = $attrs['metadata'] ?? ($existing?->metadata ?? []);
        if (! is_array($metadata)) {
            $metadata = [];
        }
        $metadata['progress_mode'] = $mode;
        $attrs['metadata'] = $metadata;

        return $attrs;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveProgressMode(array $data, ?Goal $existing): string
    {
        if (isset($data['progress_mode'])) {
            $mode = strtolower(trim((string) $data['progress_mode']));
            if (! in_array($mode, [self::PROGRESS_MANUAL, self::PROGRESS_LINKED], true)) {
                throw ValidationException::withMessages([
                    'progress_mode' => ['progress_mode inválido. Use manual ou linked.'],
                ]);
            }

            return $mode;
        }

        if (array_key_exists('category_id', $data) || array_key_exists('linked_description_pattern', $data)) {
            $categoryId = $data['category_id'] ?? $existing?->category_id;
            $pattern = $data['linked_description_pattern'] ?? $existing?->linked_description_pattern;
            $hasCategory = $categoryId !== null && $categoryId !== '';
            $hasPattern = is_string($pattern) ? trim($pattern) !== '' : filled($pattern);

            return ($hasCategory || $hasPattern) ? self::PROGRESS_LINKED : self::PROGRESS_MANUAL;
        }

        if ($existing !== null) {
            return $this->progressMode($existing);
        }

        return self::PROGRESS_MANUAL;
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}

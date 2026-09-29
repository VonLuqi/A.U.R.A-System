<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\UsageLimitExceededException;
use App\Models\RoleLimit;
use App\Models\User;
use Carbon\Carbon;

/**
 * Per-role usage quotas (PLAN_EXPANSAO §2.1 / §2.4).
 *
 * Convention: limit `0` = unlimited. Counters live on `users`
 * (`uploads_used`, `manual_transactions_used`) with monthly window
 * via `quota_period_starts_at` (APP_TIMEZONE).
 */
final class UsageLimitService
{
    public const METRIC_UPLOADS = 'uploads';

    public const METRIC_MANUAL_TRANSACTIONS = 'manual_transactions';

    /**
     * @var list<string>
     */
    public const METRICS = [
        self::METRIC_UPLOADS,
        self::METRIC_MANUAL_TRANSACTIONS,
    ];

    public function resetPeriodIfNeeded(User $user): void
    {
        $tz = (string) config('app.timezone', 'America/Sao_Paulo');
        $now = Carbon::now($tz);
        $periodStart = $user->quota_period_starts_at;

        if ($periodStart === null) {
            $user->forceFill([
                'quota_period_starts_at' => $now->copy()->startOfMonth(),
                'uploads_used' => 0,
                'manual_transactions_used' => 0,
            ])->save();

            return;
        }

        $periodStart = $periodStart->timezone($tz);
        if ($periodStart->lt($now->copy()->startOfMonth())) {
            $user->forceFill([
                'quota_period_starts_at' => $now->copy()->startOfMonth(),
                'uploads_used' => 0,
                'manual_transactions_used' => 0,
            ])->save();
        }
    }

    /**
     * @throws UsageLimitExceededException
     */
    public function assertCan(User $user, string $metric): void
    {
        $this->assertKnownMetric($metric);
        $this->resetPeriodIfNeeded($user);
        $user->refresh();

        $limit = $this->limitFor($user, $metric);
        if (RoleLimit::isUnlimited($limit)) {
            return;
        }

        $used = $this->usedFor($user, $metric);
        if ($used >= $limit) {
            throw new UsageLimitExceededException(
                metric: $metric,
                limit: $limit,
                used: $used,
                message: $this->messageFor($metric),
            );
        }
    }

    public function increment(User $user, string $metric): void
    {
        $this->assertKnownMetric($metric);
        $this->resetPeriodIfNeeded($user);

        $column = $this->columnFor($metric);
        $user->increment($column);
        $user->refresh();
    }

    /**
     * Admin reset of usage counters for the current quota window.
     */
    public function resetCounters(User $user): void
    {
        $tz = (string) config('app.timezone', 'America/Sao_Paulo');

        $user->forceFill([
            'uploads_used' => 0,
            'manual_transactions_used' => 0,
            'quota_period_starts_at' => Carbon::now($tz)->startOfMonth(),
        ])->save();
    }

    /**
     * Snapshot of limits for API resources (0 = unlimited).
     *
     * @return array{
     *     max_uploads: int,
     *     max_manual_transactions: int,
     *     max_date_range_days: int,
     *     max_goals: int,
     *     max_aliases: int
     * }
     */
    public function limitsSnapshot(User $user): array
    {
        $role = $user->role?->value ?? UserRole::Visitor->value;
        $fromDb = RoleLimit::query()->where('role', $role)->first();
        $fromConfig = config('aura.limits.'.$role, []);

        return [
            'max_uploads' => (int) ($fromDb?->max_uploads ?? $fromConfig['max_uploads'] ?? 0),
            'max_manual_transactions' => (int) ($fromDb?->max_manual_transactions ?? $fromConfig['max_manual_transactions'] ?? 0),
            'max_date_range_days' => (int) ($fromDb?->max_date_range_days ?? $fromConfig['max_date_range_days'] ?? 0),
            'max_goals' => (int) ($fromDb?->max_goals ?? $fromConfig['max_goals'] ?? 0),
            'max_aliases' => (int) ($fromConfig['max_aliases'] ?? 0),
        ];
    }

    public function can(User $user, string $metric): bool
    {
        try {
            $this->assertCan($user, $metric);

            return true;
        } catch (UsageLimitExceededException) {
            return false;
        }
    }

    public function remaining(User $user, string $metric): ?int
    {
        $this->assertKnownMetric($metric);
        $this->resetPeriodIfNeeded($user);
        $user->refresh();

        $limit = $this->limitFor($user, $metric);
        if (RoleLimit::isUnlimited($limit)) {
            return null;
        }

        return max(0, $limit - $this->usedFor($user, $metric));
    }

    public function limitFor(User $user, string $metric): int
    {
        $limits = $this->roleLimitsBag($user);

        return match ($metric) {
            self::METRIC_UPLOADS => (int) $limits['max_uploads'],
            self::METRIC_MANUAL_TRANSACTIONS => (int) $limits['max_manual_transactions'],
            default => 0,
        };
    }

    /**
     * Max inclusive calendar days for from/to filters (`0` = unlimited).
     */
    public function dateRangeDaysLimit(User $user): int
    {
        return (int) $this->roleLimitsBag($user)['max_date_range_days'];
    }

    /**
     * Inclusive day span between two Y-m-d (or parseable) dates.
     */
    public function inclusiveDaySpan(string $from, string $to): int
    {
        $tz = (string) config('app.timezone', 'America/Sao_Paulo');
        $fromDate = Carbon::parse($from, $tz)->startOfDay();
        $toDate = Carbon::parse($to, $tz)->startOfDay();

        if ($toDate->lt($fromDate)) {
            return 0;
        }

        return (int) $fromDate->diffInDays($toDate) + 1;
    }

    /**
     * Whether the inclusive from/to span fits the role's max_date_range_days.
     */
    public function isDateRangeAllowed(User $user, string $from, string $to): bool
    {
        $max = $this->dateRangeDaysLimit($user);
        if (RoleLimit::isUnlimited($max)) {
            return true;
        }

        return $this->inclusiveDaySpan($from, $to) <= $max;
    }

    /**
     * Resolved numeric limits for the user's role (DB row preferred, config fallback).
     *
     * @return array{
     *     max_uploads: int,
     *     max_manual_transactions: int,
     *     max_date_range_days: int,
     *     max_goals: int,
     *     max_aliases: int
     * }
     */
    public function roleLimitsBag(User $user): array
    {
        return $this->limitsSnapshot($user);
    }

    /**
     * Stock limit on total aliases (not monthly). `0` = unlimited.
     *
     * @throws UsageLimitExceededException
     */
    public function assertCanCreateAlias(User $user): void
    {
        $limit = (int) $this->roleLimitsBag($user)['max_aliases'];
        if (RoleLimit::isUnlimited($limit)) {
            return;
        }

        $used = $this->aliasesUsed($user);
        if ($used >= $limit) {
            throw new UsageLimitExceededException(
                metric: 'aliases',
                limit: $limit,
                used: $used,
                message: 'Limite de apelidos/regras atingido para o seu perfil.',
            );
        }
    }

    public function aliasesUsed(User $user): int
    {
        return (int) $user->transactionAliases()->count();
    }

    public function aliasesRemaining(User $user): ?int
    {
        $limit = (int) $this->roleLimitsBag($user)['max_aliases'];
        if (RoleLimit::isUnlimited($limit)) {
            return null;
        }

        return max(0, $limit - $this->aliasesUsed($user));
    }

    /**
     * Stock limit on total goals (not monthly). `0` = unlimited.
     *
     * @throws UsageLimitExceededException
     */
    public function assertCanCreateGoal(User $user): void
    {
        $limit = (int) $this->roleLimitsBag($user)['max_goals'];
        if (RoleLimit::isUnlimited($limit)) {
            return;
        }

        $used = $this->goalsUsed($user);
        if ($used >= $limit) {
            throw new UsageLimitExceededException(
                metric: 'goals',
                limit: $limit,
                used: $used,
                message: 'Limite de metas financeiras atingido para o seu perfil.',
            );
        }
    }

    public function goalsUsed(User $user): int
    {
        return (int) $user->goals()->count();
    }

    public function goalsRemaining(User $user): ?int
    {
        $limit = (int) $this->roleLimitsBag($user)['max_goals'];
        if (RoleLimit::isUnlimited($limit)) {
            return null;
        }

        return max(0, $limit - $this->goalsUsed($user));
    }

    public function usedFor(User $user, string $metric): int
    {
        return match ($metric) {
            self::METRIC_UPLOADS => (int) $user->uploads_used,
            self::METRIC_MANUAL_TRANSACTIONS => (int) $user->manual_transactions_used,
            default => 0,
        };
    }

    private function columnFor(string $metric): string
    {
        return match ($metric) {
            self::METRIC_UPLOADS => 'uploads_used',
            self::METRIC_MANUAL_TRANSACTIONS => 'manual_transactions_used',
            default => throw new \InvalidArgumentException("Unknown metric [{$metric}]."),
        };
    }

    private function messageFor(string $metric): string
    {
        return match ($metric) {
            self::METRIC_UPLOADS => 'Limite de uploads do período atingido.',
            self::METRIC_MANUAL_TRANSACTIONS => 'Limite de transações manuais do período atingido.',
            default => 'Limite de uso excedido para este recurso.',
        };
    }

    private function assertKnownMetric(string $metric): void
    {
        if (! in_array($metric, self::METRICS, true)) {
            throw new \InvalidArgumentException("Unknown metric [{$metric}].");
        }
    }
}

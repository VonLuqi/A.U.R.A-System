<?php

namespace App\Services;

use App\Enums\GoalKind;
use App\Enums\GoalStatus;
use App\Models\Goal;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Goal progress from transaction writes (PLAN_EXPANSAO §3.2 hook / §7.1).
 *
 * Linked goals (category_id and/or linked_description_pattern) are recalculated
 * from matching transactions. Manual-only goals (no link) are left untouched
 * on transaction hooks — use GoalService::recalculate for status sync.
 *
 * - savings → sum of matching credits
 * - debt_payoff → sum of matching debits
 * - auto-complete when current_amount >= target_amount (active|completed only)
 */
final class GoalProgressService
{
    /**
     * Alias used by ManualTransactionService (§3.2).
     */
    public function touchFromTransaction(Transaction $transaction): void
    {
        $this->onTransactionWritten($transaction);
    }

    /**
     * Recalculate active/completed linked goals for the transaction owner (§7.1).
     */
    public function onTransactionWritten(Transaction $transaction): void
    {
        $this->recalculateLinkedForUser((int) $transaction->user_id);
    }

    public function recalculateLinkedForUser(int $userId): void
    {
        $goals = Goal::query()
            ->forUser($userId)
            ->whereIn('status', [GoalStatus::Active, GoalStatus::Completed])
            ->get()
            ->filter(fn (Goal $goal): bool => $goal->isLinked());

        foreach ($goals as $goal) {
            $this->recalculateLinked($goal);
        }
    }

    public function recalculateLinked(Goal $goal): void
    {
        if (! $goal->isLinked()) {
            return;
        }

        $sum = $this->sumMatchingAmount($goal);
        $goal->current_amount = number_format($sum, 2, '.', '');
        $this->syncCompletionStatus($goal);
        $goal->save();
    }

    /**
     * Sync status from current_amount vs target for active|completed goals.
     * Paused/cancelled keep their status; amount may still be updated by caller.
     */
    public function syncCompletionStatus(Goal $goal): void
    {
        $status = $goal->status instanceof GoalStatus
            ? $goal->status
            : GoalStatus::tryFrom((string) $goal->status);

        if (! in_array($status, [GoalStatus::Active, GoalStatus::Completed], true)) {
            return;
        }

        $target = (float) $goal->target_amount;
        $current = (float) $goal->current_amount;

        if ($target > 0 && $current >= $target) {
            $goal->status = GoalStatus::Completed;

            return;
        }

        if ($status === GoalStatus::Completed) {
            $goal->status = GoalStatus::Active;
        }
    }

    public function sumMatchingAmount(Goal $goal): float
    {
        $rows = $this->matchingTransactions($goal);

        return round((float) $rows->sum(fn (Transaction $tx): float => (float) $tx->amount), 2);
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function matchingTransactions(Goal $goal): Collection
    {
        $type = $goal->kind === GoalKind::DebtPayoff ? 'debit' : 'credit';

        $query = Transaction::query()
            ->forUser((int) $goal->user_id)
            ->where('type', $type);

        if ($goal->category_id !== null) {
            $query->where('category_id', $goal->category_id);
        }

        if ($goal->created_at !== null) {
            $query->whereDate('occurred_on', '>=', $goal->created_at->toDateString());
        }

        if ($goal->deadline_on !== null) {
            $query->whereDate('occurred_on', '<=', $goal->deadline_on->format('Y-m-d'));
        }

        $pattern = filled($goal->linked_description_pattern)
            ? trim((string) $goal->linked_description_pattern)
            : null;

        if ($pattern !== null && ! $this->isRegexPattern($pattern)) {
            $query->where('description', 'like', $pattern);
        }

        $rows = $query->get();

        if ($pattern !== null && $this->isRegexPattern($pattern)) {
            [$body, $flags] = $this->parseRegexPattern($pattern);
            if ($body === null || @preg_match('/'.$body.'/'.$flags, '') === false) {
                return collect();
            }

            $rows = $rows->filter(
                fn (Transaction $tx): bool => (bool) preg_match('/'.$body.'/'.$flags, (string) $tx->description)
            )->values();
        }

        return $rows;
    }

    private function isRegexPattern(string $pattern): bool
    {
        return (bool) preg_match('#^/.+/[imsuxADSUXJ]*$#u', $pattern);
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private function parseRegexPattern(string $pattern): array
    {
        if (! preg_match('#^/(.+)/([imsuxADSUXJ]*)$#u', $pattern, $matches)) {
            return [null, 'iu'];
        }

        $flags = $matches[2] !== '' ? $matches[2] : 'iu';

        return [$matches[1], $flags];
    }
}

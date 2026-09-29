<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\TransactionAlias;
use Illuminate\Support\Facades\DB;

/**
 * Apply a new alias to recent existing transactions (PLAN_EXPANSAO §4.3).
 *
 * Runs synchronously with a hard row limit — no queue/Redis (HostGator-safe).
 * Matches against `raw_payload.original_description` when present, else
 * `description`. Does not rewrite `unique_hash` (import idempotency).
 *
 * @phpstan-type ApplyResult array{scanned: int, updated: int, limit: int}
 */
final class AliasRetroactiveApplyService
{
    public const DEFAULT_LIMIT = 500;

    public function __construct(
        private readonly GoalProgressService $goalProgress,
    ) {}

    /**
     * @return array{scanned: int, updated: int, limit: int}
     */
    public function apply(TransactionAlias $alias, ?int $limit = null): array
    {
        $limit = max(1, $limit ?? (int) config('aura.aliases.retroactive_limit', self::DEFAULT_LIMIT));

        $transactions = Transaction::query()
            ->forUser((int) $alias->user_id)
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $scanned = $transactions->count();
        $updated = 0;

        DB::transaction(function () use ($alias, $transactions, &$updated): void {
            foreach ($transactions as $transaction) {
                if (! $this->applyToTransaction($alias, $transaction)) {
                    continue;
                }
                $updated++;
            }
        });

        if ($updated > 0) {
            $this->goalProgress->recalculateLinkedForUser((int) $alias->user_id);
        }

        return [
            'scanned' => $scanned,
            'updated' => $updated,
            'limit' => $limit,
        ];
    }

    public function applyToTransaction(TransactionAlias $alias, Transaction $transaction): bool
    {
        $payload = is_array($transaction->raw_payload) ? $transaction->raw_payload : [];
        $original = isset($payload['original_description']) && is_string($payload['original_description'])
            && $payload['original_description'] !== ''
            ? $payload['original_description']
            : (string) $transaction->description;

        if (! $alias->matches($original)) {
            return false;
        }

        if (! isset($payload['original_description'])) {
            $payload['original_description'] = $original;
        }
        $payload['alias_id'] = (int) $alias->id;

        $transaction->description = (string) $alias->display_name;
        if ($alias->category_id !== null) {
            $transaction->category_id = (int) $alias->category_id;
        }
        $transaction->raw_payload = $payload;
        $transaction->save();

        return true;
    }
}

<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Apply a category to other transactions with the same merchant description.
 *
 * Matches against raw_payload.original_description when present, else description
 * (case-insensitive). Synchronous with a hard row limit — HostGator-safe.
 *
 * @phpstan-type ApplyResult array{scanned: int, updated: int, limit: int}
 */
final class CategoryBulkApplyService
{
    public const DEFAULT_LIMIT = 500;

    public function __construct(
        private readonly GoalProgressService $goalProgress,
    ) {}

    /**
     * @return array{scanned: int, updated: int, limit: int}
     */
    public function applyToMatchingDescription(
        User $user,
        Transaction $source,
        ?int $categoryId,
        ?int $limit = null,
    ): array {
        $limit = max(1, $limit ?? (int) config('aura.aliases.retroactive_limit', self::DEFAULT_LIMIT));
        $needle = $this->matchKey($source);

        if ($needle === '') {
            return [
                'scanned' => 0,
                'updated' => 0,
                'limit' => $limit,
            ];
        }

        $transactions = Transaction::query()
            ->forUser($user)
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $scanned = $transactions->count();
        $updated = 0;

        DB::transaction(function () use ($transactions, $needle, $categoryId, $source, &$updated): void {
            foreach ($transactions as $transaction) {
                if ((int) $transaction->id === (int) $source->id) {
                    continue;
                }

                if (! $this->matches($transaction, $needle)) {
                    continue;
                }

                $current = $transaction->category_id !== null ? (int) $transaction->category_id : null;
                if ($current === $categoryId) {
                    continue;
                }

                $transaction->category_id = $categoryId;
                $transaction->save();
                $updated++;
            }
        });

        if ($updated > 0) {
            $this->goalProgress->recalculateLinkedForUser((int) $user->id);
        }

        return [
            'scanned' => $scanned,
            'updated' => $updated,
            'limit' => $limit,
        ];
    }

    public function matchKey(Transaction $transaction): string
    {
        $payload = is_array($transaction->raw_payload) ? $transaction->raw_payload : [];
        $original = isset($payload['original_description'])
            && is_string($payload['original_description'])
            && $payload['original_description'] !== ''
            ? $payload['original_description']
            : (string) $transaction->description;

        return trim($original);
    }

    private function matches(Transaction $transaction, string $needle): bool
    {
        return strcasecmp($this->matchKey($transaction), $needle) === 0;
    }
}

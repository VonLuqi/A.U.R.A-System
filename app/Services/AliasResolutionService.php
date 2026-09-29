<?php

namespace App\Services;

use App\DTOs\AliasMatch;
use App\Models\TransactionAlias;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolve display name / category from user aliases (PLAN_EXPANSAO §4.1).
 *
 * Active aliases are loaded once per user per service instance (request-scoped
 * memoization) ordered by priority ASC, then id ASC — first match wins.
 * Cache entries are keyed by user + max(updated_at) so mutations invalidate.
 */
final class AliasResolutionService
{
    /**
     * @var array<int, array{version: string, aliases: Collection<int, TransactionAlias>}>
     */
    private array $cache = [];

    public function resolve(User $user, string $rawDescription): ?AliasMatch
    {
        foreach ($this->aliasesFor($user) as $alias) {
            if (! $alias->matches($rawDescription)) {
                continue;
            }

            return new AliasMatch(
                aliasId: (int) $alias->id,
                displayName: (string) $alias->display_name,
                categoryId: $alias->category_id !== null ? (int) $alias->category_id : null,
            );
        }

        return null;
    }

    /**
     * @return Collection<int, TransactionAlias>
     */
    public function aliasesFor(User $user): Collection
    {
        $userId = (int) $user->id;
        $version = $this->cacheVersionFor($user);

        $hit = $this->cache[$userId] ?? null;
        if ($hit !== null && $hit['version'] === $version) {
            return $hit['aliases'];
        }

        $aliases = TransactionAlias::query()
            ->forResolution($user)
            ->get();

        $this->cache[$userId] = [
            'version' => $version,
            'aliases' => $aliases,
        ];

        return $aliases;
    }

    public function forget(User|int|null $user = null): void
    {
        if ($user === null) {
            $this->cache = [];

            return;
        }

        unset($this->cache[$user instanceof User ? (int) $user->id : (int) $user]);
    }

    /**
     * Invalidate when rows change even if updated_at stays in the same second.
     */
    private function cacheVersionFor(User $user): string
    {
        $row = TransactionAlias::query()
            ->forUser($user)
            ->selectRaw('COUNT(*) as aggregate_count, COALESCE(SUM(is_active), 0) as active_sum, MAX(updated_at) as max_updated')
            ->first();

        return implode(':', [
            (string) ($row->aggregate_count ?? 0),
            (string) ($row->active_sum ?? 0),
            (string) ($row->max_updated ?? 'none'),
        ]);
    }
}

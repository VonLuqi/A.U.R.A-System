<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Models\CreditCard;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Credit cards CRUD (PLAN_CARTOES_EMPRESTIMOS §3.1).
 */
final class CreditCardService
{
    public function __construct(
        private readonly UsageLimitService $usageLimits,
    ) {}

    /**
     * @param  array{q?: string|null, is_active?: bool|null}  $filters
     * @return Builder<CreditCard>
     */
    public function queryForUser(User $user, array $filters = []): Builder
    {
        $query = CreditCard::query()
            ->forUser($user)
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->orderBy('id');

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        $q = $filters['q'] ?? null;
        if (is_string($q) && $q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('name', 'like', $like)
                    ->orWhere('last_four', 'like', $like);
            });
        }

        return $query;
    }

    /**
     * Resolve which card to stamp on csv_credit_card imports.
     *
     * Priority: explicit override (owned) → active is_default → sole active → null.
     */
    public function resolveForImport(User $user, ?int $overrideId = null): ?int
    {
        if ($overrideId !== null) {
            $owned = CreditCard::query()
                ->forUser($user)
                ->whereKey($overrideId)
                ->exists();

            return $owned ? $overrideId : null;
        }

        $defaultId = CreditCard::query()
            ->forUser($user)
            ->active()
            ->default()
            ->value('id');

        if ($defaultId !== null) {
            return (int) $defaultId;
        }

        $activeIds = CreditCard::query()
            ->forUser($user)
            ->active()
            ->orderBy('id')
            ->pluck('id');

        if ($activeIds->count() === 1) {
            return (int) $activeIds->first();
        }

        return null;
    }

    /**
     * @param  array{
     *     name: string,
     *     limit_amount?: numeric|null,
     *     currency?: string|null,
     *     closing_day: int,
     *     due_day: int,
     *     last_four?: string|null,
     *     is_active?: bool|null,
     *     is_default?: bool|null,
     *     notes?: string|null
     * }  $data
     */
    public function create(User $user, array $data): CreditCard
    {
        $this->usageLimits->assertCanCreateCreditCard($user);

        return DB::transaction(function () use ($user, $data): CreditCard {
            $normalized = $this->normalize($data, creating: true);

            $hasAnyCard = CreditCard::query()->forUser($user)->exists();
            if (! array_key_exists('is_default', $normalized)) {
                $normalized['is_default'] = ! $hasAnyCard;
            }

            if (($normalized['is_active'] ?? true) === false) {
                $normalized['is_default'] = false;
            }

            if (($normalized['is_default'] ?? false) === true) {
                $this->clearDefaultsForUser((int) $user->id);
            }

            return CreditCard::query()->create([
                'user_id' => $user->id,
                ...$normalized,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, CreditCard $card, array $data): CreditCard
    {
        if ((int) $card->user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'credit_card' => ['Cartão não pertence ao usuário autenticado.'],
            ]);
        }

        return DB::transaction(function () use ($user, $card, $data): CreditCard {
            $normalized = $this->normalize($data, creating: false);

            $willBeActive = array_key_exists('is_active', $normalized)
                ? (bool) $normalized['is_active']
                : (bool) $card->is_active;

            if (! $willBeActive) {
                $normalized['is_default'] = false;
            }

            $willBeDefault = array_key_exists('is_default', $normalized)
                ? (bool) $normalized['is_default']
                : (bool) $card->is_default;

            if ($willBeDefault && $willBeActive) {
                $this->clearDefaultsForUser((int) $user->id, exceptId: (int) $card->id);
                $normalized['is_default'] = true;
            }

            $card->fill($normalized)->save();

            return $card->fresh();
        });
    }

    public function delete(CreditCard $card): void
    {
        $openLoans = $card->loans()
            ->whereIn('status', [LoanStatus::Open, LoanStatus::Partial])
            ->exists();

        if ($openLoans) {
            throw ValidationException::withMessages([
                'credit_card' => ['Não é possível excluir: há cobranças em aberto vinculadas a este cartão.'],
            ]);
        }

        $userId = (int) $card->user_id;
        $wasDefault = (bool) $card->is_default;

        $card->delete();

        if ($wasDefault) {
            $next = CreditCard::query()
                ->forUser($userId)
                ->active()
                ->orderBy('id')
                ->first();

            if ($next !== null) {
                $next->forceFill(['is_default' => true])->save();
            }
        }
    }

    /**
     * Vincula várias transações do usuário a este cartão (HostGator-safe, limite duro).
     *
     * @param  list<int>  $transactionIds
     * @return array{linked: int, skipped: int, limit: int}
     */
    public function linkTransactions(User $user, CreditCard $card, array $transactionIds): array
    {
        if ((int) $card->user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'credit_card' => ['Cartão não pertence ao usuário autenticado.'],
            ]);
        }

        $limit = 100;
        $ids = array_values(array_unique(array_map('intval', $transactionIds)));
        $ids = array_slice($ids, 0, $limit);

        if ($ids === []) {
            return ['linked' => 0, 'skipped' => 0, 'limit' => $limit];
        }

        $linked = 0;
        $skipped = 0;

        DB::transaction(function () use ($user, $card, $ids, &$linked, &$skipped): void {
            $rows = Transaction::query()
                ->forUser($user)
                ->whereIn('id', $ids)
                ->get(['id', 'user_id', 'credit_card_id', 'type']);

            $found = $rows->keyBy('id');

            foreach ($ids as $id) {
                $tx = $found->get($id);
                if ($tx === null) {
                    $skipped++;
                    continue;
                }

                if ((string) $tx->type !== 'debit') {
                    $skipped++;
                    continue;
                }

                if ((int) ($tx->credit_card_id ?? 0) === (int) $card->id) {
                    $skipped++;
                    continue;
                }

                $tx->credit_card_id = $card->id;
                $tx->save();
                $linked++;
            }
        });

        return [
            'linked' => $linked,
            'skipped' => $skipped,
            'limit' => $limit,
        ];
    }

    private function clearDefaultsForUser(int $userId, ?int $exceptId = null): void
    {
        $query = CreditCard::query()
            ->where('user_id', $userId)
            ->where('is_default', true);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        $query->update(['is_default' => false]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, bool $creating): array
    {
        $out = [];

        if ($creating || array_key_exists('name', $data)) {
            $out['name'] = trim((string) $data['name']);
        }
        if ($creating || array_key_exists('limit_amount', $data)) {
            $out['limit_amount'] = array_key_exists('limit_amount', $data) && $data['limit_amount'] !== null
                ? number_format((float) $data['limit_amount'], 2, '.', '')
                : null;
        }
        if ($creating || array_key_exists('currency', $data)) {
            $out['currency'] = strtoupper((string) ($data['currency'] ?? 'BRL'));
        }
        if ($creating || array_key_exists('closing_day', $data)) {
            $out['closing_day'] = (int) $data['closing_day'];
        }
        if ($creating || array_key_exists('due_day', $data)) {
            $out['due_day'] = (int) $data['due_day'];
        }
        if ($creating || array_key_exists('last_four', $data)) {
            $out['last_four'] = isset($data['last_four']) && $data['last_four'] !== null && $data['last_four'] !== ''
                ? (string) $data['last_four']
                : null;
        }
        if ($creating || array_key_exists('is_active', $data)) {
            $out['is_active'] = array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : true;
        }
        if (array_key_exists('is_default', $data)) {
            $out['is_default'] = (bool) $data['is_default'];
        }
        if ($creating || array_key_exists('notes', $data)) {
            $out['notes'] = isset($data['notes']) && is_string($data['notes']) && $data['notes'] !== ''
                ? $data['notes']
                : null;
        }

        return $out;
    }
}

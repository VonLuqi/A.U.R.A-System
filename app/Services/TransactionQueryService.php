<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use App\Services\Concerns\AppliesTransactionFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filtered transaction queries for the authenticated user (Etapa C §5.4.2 / PLAN_EXPANSAO §2.2).
 *
 * Ownership is row-level via `transactions.user_id` (not only via statement_imports),
 * so manual transactions (`source_kind=manual`, nullable import) stay in scope.
 *
 * @phpstan-type TransactionFilters array{
 *     from?: ?string,
 *     to?: ?string,
 *     type?: ?string,
 *     category_id?: ?int,
 *     q?: ?string,
 *     statement_import_id?: ?int,
 *     credit_card_id?: ?int,
 *     loan_id?: ?int,
 *     debtor_id?: ?int,
 *     has_loan?: bool|null,
 *     sort?: string,
 *     direction?: string,
 *     group_by?: string
 * }
 */
final class TransactionQueryService
{
    use AppliesTransactionFilters;

    /**
     * Base listing query: ownership + eager category + filters + sort.
     *
     * @param  TransactionFilters  $filters
     * @return Builder<Transaction>
     */
    public function forUser(User $user, array $filters = []): Builder
    {
        $query = Transaction::query()
            ->with([
                'category:id,name,slug,type,color',
                'creditCard:id,name',
                'loan:id,debtor_id,debtor_name,status',
            ])
            ->forUser($user);

        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters);

        return $query;
    }

    /**
     * Same filter base without sort (for analytics aggregations — §5.5.2).
     *
     * @param  TransactionFilters  $filters
     * @return Builder<Transaction>
     */
    public function baseForUser(User $user, array $filters = []): Builder
    {
        $query = Transaction::query()->forUser($user);

        return $this->applyFilters($query, $filters);
    }

    /**
     * @param  Builder<Transaction>  $query
     * @param  TransactionFilters  $filters
     * @return Builder<Transaction>
     */
    public function applySort(Builder $query, array $filters): Builder
    {
        $sort = $filters['sort'] ?? 'occurred_on';
        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        if (! in_array($sort, ['occurred_on', 'amount', 'created_at'], true)) {
            $sort = 'occurred_on';
        }

        return $query->orderBy($sort, $direction)->orderBy('id', $direction);
    }
}

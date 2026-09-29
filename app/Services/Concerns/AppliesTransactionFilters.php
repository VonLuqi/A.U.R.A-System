<?php

namespace App\Services\Concerns;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared filter application for listing + analytics (Etapa C §5.5.2).
 *
 * @phpstan-type TransactionFilters array{
 *     from?: ?string,
 *     to?: ?string,
 *     type?: ?string,
 *     category_id?: ?int,
 *     q?: ?string,
 *     statement_import_id?: ?int,
 *     sort?: string,
 *     direction?: string,
 *     group_by?: string
 * }
 */
trait AppliesTransactionFilters
{
    /**
     * @param  Builder<Transaction>  $query
     * @param  TransactionFilters  $filters
     * @return Builder<Transaction>
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        if (is_string($from) && $from !== '' && is_string($to) && $to !== '') {
            $query->betweenDates($from, $to);
        } elseif (is_string($from) && $from !== '') {
            $query->whereDate('occurred_on', '>=', $from);
        } elseif (is_string($to) && $to !== '') {
            $query->whereDate('occurred_on', '<=', $to);
        }

        $type = $filters['type'] ?? null;
        if ($type === 'credit' || $type === 'debit') {
            // Qualify: categories.type collides on analytics joins (§5.5.2).
            $query->where('transactions.type', $type);
        }

        $categoryId = $filters['category_id'] ?? null;
        if ($categoryId !== null) {
            $query->where('transactions.category_id', (int) $categoryId);
        }

        $q = $filters['q'] ?? null;
        if (is_string($q) && $q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where('transactions.description', 'like', $like);
        }

        $importId = $filters['statement_import_id'] ?? null;
        if ($importId !== null) {
            // Combined with forUser()/user_id: foreign import ids simply yield empty sets.
            $query->where('transactions.statement_import_id', (int) $importId);
        }

        return $query;
    }
}

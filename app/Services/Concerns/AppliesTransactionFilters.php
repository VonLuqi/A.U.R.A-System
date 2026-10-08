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
 *     credit_card_id?: ?int,
 *     credit_card_ids?: list<int>|null,
 *     include_uncarded?: bool|null,
 *     loan_id?: ?int,
 *     debtor_id?: ?int,
 *     has_loan?: bool|null,
 *     has_credit_card?: bool|null,
 *     sort?: string,
 *     direction?: string,
 *     group_by?: string,
 *     cycle_type_ranges?: array{
 *         debit: array{0: string, 1: string},
 *         credit: array{0: string, 1: string}
 *     }|null
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
        $type = $filters['type'] ?? null;
        $cycleRanges = $filters['cycle_type_ranges'] ?? null;
        $useCycleSplit = is_array($cycleRanges)
            && ($type === null || $type === '')
            && isset($cycleRanges['debit'][0], $cycleRanges['debit'][1], $cycleRanges['credit'][0], $cycleRanges['credit'][1]);

        if ($useCycleSplit) {
            // Meu ciclo + Todos: saídas no ciclo de gastos, entradas no ciclo de receita.
            $query->where(function (Builder $outer) use ($cycleRanges): void {
                $outer->where(function (Builder $debit) use ($cycleRanges): void {
                    $debit->where('transactions.type', 'debit')
                        ->whereDate('transactions.occurred_on', '>=', $cycleRanges['debit'][0])
                        ->whereDate('transactions.occurred_on', '<=', $cycleRanges['debit'][1]);
                })->orWhere(function (Builder $credit) use ($cycleRanges): void {
                    $credit->where('transactions.type', 'credit')
                        ->whereDate('transactions.occurred_on', '>=', $cycleRanges['credit'][0])
                        ->whereDate('transactions.occurred_on', '<=', $cycleRanges['credit'][1]);
                });
            });
        } else {
            $from = $filters['from'] ?? null;
            $to = $filters['to'] ?? null;

            if (is_string($from) && $from !== '' && is_string($to) && $to !== '') {
                $query->betweenDates($from, $to);
            } elseif (is_string($from) && $from !== '') {
                $query->whereDate('occurred_on', '>=', $from);
            } elseif (is_string($to) && $to !== '') {
                $query->whereDate('occurred_on', '<=', $to);
            }

            if ($type === 'credit' || $type === 'debit') {
                // Qualify: categories.type collides on analytics joins (§5.5.2).
                $query->where('transactions.type', $type);
            }
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

        $creditCardIds = $filters['credit_card_ids'] ?? null;
        if (is_array($creditCardIds) && $creditCardIds !== []) {
            $ids = array_values(array_unique(array_map('intval', $creditCardIds)));
            $includeUncarded = (bool) ($filters['include_uncarded'] ?? false);

            $query->where(function (Builder $cardQuery) use ($ids, $includeUncarded): void {
                $cardQuery->whereIn('transactions.credit_card_id', $ids);
                if ($includeUncarded) {
                    $cardQuery->orWhereNull('transactions.credit_card_id');
                }
            });
        } else {
            $creditCardId = $filters['credit_card_id'] ?? null;
            if ($creditCardId !== null) {
                $query->where('transactions.credit_card_id', (int) $creditCardId);
            }
        }

        $loanId = $filters['loan_id'] ?? null;
        if ($loanId !== null) {
            $query->where('transactions.loan_id', (int) $loanId);
        }

        $debtorId = $filters['debtor_id'] ?? null;
        if ($debtorId !== null) {
            $query->whereHas('loan', function (Builder $loanQuery) use ($debtorId): void {
                $loanQuery->where('loans.debtor_id', (int) $debtorId);
            });
        }

        if (array_key_exists('has_loan', $filters) && $filters['has_loan'] !== null) {
            if ($filters['has_loan']) {
                $query->whereNotNull('transactions.loan_id');
            } else {
                $query->whereNull('transactions.loan_id');
            }
        }

        if (array_key_exists('has_credit_card', $filters) && $filters['has_credit_card'] !== null) {
            if ($filters['has_credit_card']) {
                $query->whereNotNull('transactions.credit_card_id');
            } else {
                $query->whereNull('transactions.credit_card_id');
            }
        }

        return $query;
    }
}

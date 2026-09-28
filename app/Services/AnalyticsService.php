<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Arr;

/**
 * Dashboard aggregates: cards, temporal series, by-category (Etapa C §5.5.2).
 *
 * Reuses TransactionQueryService::baseForUser (AppliesTransactionFilters).
 *
 * @phpstan-type AnalyticsFilters array{
 *     from?: ?string,
 *     to?: ?string,
 *     type?: ?string,
 *     category_id?: ?int,
 *     q?: ?string,
 *     statement_import_id?: ?int,
 *     group_by?: string
 * }
 */
final class AnalyticsService
{
    public function __construct(
        private readonly TransactionQueryService $transactions,
    ) {}

    /**
     * @param  AnalyticsFilters  $filters
     * @return array{
     *     cards: array{
     *         balance: string,
     *         total_income: string,
     *         total_expense: string,
     *         transactions_count: int
     *     },
     *     series: list<array{period: string, income: string, expense: string, balance: string}>,
     *     by_category: list<array{
     *         category_id: int|null,
     *         name: string|null,
     *         color: string|null,
     *         total: string,
     *         count: int,
     *         type: string
     *     }>
     * }
     */
    public function dashboard(User $user, array $filters = []): array
    {
        $groupBy = ($filters['group_by'] ?? 'month') === 'day' ? 'day' : 'month';
        $queryFilters = Arr::except($filters, ['group_by']);

        return [
            'cards' => $this->cards($user, $queryFilters),
            'series' => $this->series($user, $queryFilters, $groupBy),
            'by_category' => $this->byCategory($user, $queryFilters),
        ];
    }

    /**
     * @param  AnalyticsFilters  $filters
     * @return array{
     *     balance: string,
     *     total_income: string,
     *     total_expense: string,
     *     transactions_count: int
     * }
     */
    public function cards(User $user, array $filters = []): array
    {
        $row = $this->transactions->baseForUser($user, $filters)
            ->toBase()
            ->selectRaw("
                COUNT(*) as transactions_count,
                COALESCE(SUM(CASE WHEN transactions.type = 'credit' THEN transactions.amount ELSE 0 END), 0) as total_income,
                COALESCE(SUM(CASE WHEN transactions.type = 'debit' THEN transactions.amount ELSE 0 END), 0) as total_expense
            ")
            ->first();

        $income = $this->money((float) ($row->total_income ?? 0));
        $expense = $this->money((float) ($row->total_expense ?? 0));
        $balance = $this->money((float) ($row->total_income ?? 0) - (float) ($row->total_expense ?? 0));

        return [
            'balance' => $balance,
            'total_income' => $income,
            'total_expense' => $expense,
            'transactions_count' => (int) ($row->transactions_count ?? 0),
        ];
    }

    /**
     * @param  AnalyticsFilters  $filters
     * @return list<array{period: string, income: string, expense: string, balance: string}>
     */
    public function series(User $user, array $filters = [], string $groupBy = 'month'): array
    {
        $periodExpr = $groupBy === 'day'
            ? "DATE_FORMAT(transactions.occurred_on, '%Y-%m-%d')"
            : "DATE_FORMAT(transactions.occurred_on, '%Y-%m')";

        $rows = $this->transactions->baseForUser($user, $filters)
            ->toBase()
            ->selectRaw("
                {$periodExpr} as period,
                COALESCE(SUM(CASE WHEN transactions.type = 'credit' THEN transactions.amount ELSE 0 END), 0) as income,
                COALESCE(SUM(CASE WHEN transactions.type = 'debit' THEN transactions.amount ELSE 0 END), 0) as expense
            ")
            ->groupByRaw($periodExpr)
            ->orderByRaw($periodExpr)
            ->get();

        $series = [];
        foreach ($rows as $row) {
            $income = (float) $row->income;
            $expense = (float) $row->expense;
            $series[] = [
                'period' => (string) $row->period,
                'income' => $this->money($income),
                'expense' => $this->money($expense),
                'balance' => $this->money($income - $expense),
            ];
        }

        return $series;
    }

    /**
     * Expense pie by default (debit only when type filter omitted).
     *
     * @param  AnalyticsFilters  $filters
     * @return list<array{
     *     category_id: int|null,
     *     name: string|null,
     *     color: string|null,
     *     total: string,
     *     count: int,
     *     type: string
     * }>
     */
    public function byCategory(User $user, array $filters = []): array
    {
        $categoryFilters = $filters;
        if (($categoryFilters['type'] ?? null) === null || $categoryFilters['type'] === '') {
            $categoryFilters['type'] = 'debit';
        }
        $effectiveType = (string) $categoryFilters['type'];

        $rows = $this->transactions->baseForUser($user, $categoryFilters)
            ->toBase()
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->selectRaw('
                transactions.category_id as category_id,
                categories.name as name,
                categories.color as color,
                COALESCE(SUM(transactions.amount), 0) as total,
                COUNT(*) as count
            ')
            ->groupBy('transactions.category_id', 'categories.name', 'categories.color')
            ->orderByDesc('total')
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'category_id' => $row->category_id !== null ? (int) $row->category_id : null,
                'name' => $row->name !== null ? (string) $row->name : null,
                'color' => $row->color !== null ? (string) $row->color : null,
                'total' => $this->money((float) $row->total),
                'count' => (int) $row->count,
                'type' => $effectiveType,
            ];
        }

        return $items;
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}

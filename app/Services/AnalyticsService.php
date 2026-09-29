<?php

namespace App\Services;

use App\Models\TransactionAlias;
use App\Models\User;
use App\Support\DateRangeQuery;
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
        private readonly DashboardGoalsAggregator $goalsAggregator,
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
     *     }>,
     *     by_alias: list<array{
     *         alias_id: int|null,
     *         name: string,
     *         color: string|null,
     *         total: string,
     *         count: int,
     *         type: string
     *     }>,
     *     goals: array{
     *         items: list<array<string, mixed>>,
     *         active_count: int,
     *         completed_count: int,
     *         paused_count: int,
     *         goals_used: int,
     *         goals_remaining: int|null,
     *         cards: array{
     *             average_progress_percent: float|null,
     *             nearest_deadline: array<string, mixed>|null
     *         }
     *     }
     * }
     */
    public function dashboard(User $user, array $filters = []): array
    {
        $groupBy = $this->resolveSeriesGroupBy($filters);
        $queryFilters = Arr::except($filters, ['group_by', 'preset']);

        return [
            'cards' => $this->cards($user, $queryFilters),
            'series' => $this->series($user, $queryFilters, $groupBy),
            'by_category' => $this->byCategory($user, $queryFilters),
            'by_alias' => $this->byAlias($user, $queryFilters),
            'goals' => $this->goalsAggregator->forUser($user),
        ];
    }

    /**
     * Prefer explicit group_by; otherwise DateRangeQuery::resolveGroupBy (≤45 → day).
     *
     * @param  AnalyticsFilters  $filters
     * @return 'day'|'month'
     */
    private function resolveSeriesGroupBy(array $filters): string
    {
        $explicit = $filters['group_by'] ?? null;
        if ($explicit === 'day' || $explicit === 'month') {
            return $explicit;
        }

        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        if (is_string($from) && $from !== '' && is_string($to) && $to !== '') {
            return DateRangeQuery::resolveGroupBy($from, $to);
        }

        return 'month';
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

    /**
     * Debit totals grouped by alias display_name (identical names merge into one bar).
     *
     * Only transactions with `raw_payload.alias_id` (matched by AliasResolutionService).
     *
     * @param  AnalyticsFilters  $filters
     * @return list<array{
     *     alias_id: int|null,
     *     name: string,
     *     color: string|null,
     *     total: string,
     *     count: int,
     *     type: string
     * }>
     */
    public function byAlias(User $user, array $filters = []): array
    {
        $aliasFilters = $filters;
        if (($aliasFilters['type'] ?? null) === null || $aliasFilters['type'] === '') {
            $aliasFilters['type'] = 'debit';
        }
        $effectiveType = (string) $aliasFilters['type'];

        $aliasNames = TransactionAlias::query()
            ->forUser($user)
            ->get(['id', 'display_name'])
            ->keyBy('id');

        if ($aliasNames->isEmpty()) {
            return [];
        }

        $transactions = $this->transactions->baseForUser($user, $aliasFilters)
            ->get(['amount', 'raw_payload']);

        /** @var array<string, array{alias_id: int, name: string, total: float, count: int}> $buckets */
        $buckets = [];

        foreach ($transactions as $transaction) {
            $payload = is_array($transaction->raw_payload) ? $transaction->raw_payload : [];
            $aliasId = isset($payload['alias_id']) ? (int) $payload['alias_id'] : null;
            if ($aliasId === null || $aliasId < 1) {
                continue;
            }

            $alias = $aliasNames->get($aliasId);
            if ($alias === null) {
                continue;
            }

            $name = trim((string) $alias->display_name);
            if ($name === '') {
                continue;
            }

            $key = mb_strtolower($name);
            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'alias_id' => $aliasId,
                    'name' => $name,
                    'total' => 0.0,
                    'count' => 0,
                ];
            }

            $buckets[$key]['total'] += (float) $transaction->amount;
            $buckets[$key]['count']++;
        }

        uasort($buckets, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        $palette = ['#DCCFFF', '#A8E6C3', '#9A9C9B', '#F5C6AA', '#B8D4E8', '#E8D5B7', '#C5B4E3'];
        $items = [];
        $index = 0;

        foreach ($buckets as $bucket) {
            $items[] = [
                'alias_id' => $bucket['alias_id'],
                'name' => $bucket['name'],
                'color' => $palette[$index % count($palette)],
                'total' => $this->money($bucket['total']),
                'count' => $bucket['count'],
                'type' => $effectiveType,
            ];
            $index++;
        }

        return $items;
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}

<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Models\CreditCard;
use App\Models\Loan;
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
 *     credit_card_id?: ?int,
 *     debtor_id?: ?int,
 *     group_by?: string
 * }
 */
final class AnalyticsService
{
    public function __construct(
        private readonly TransactionQueryService $transactions,
        private readonly DashboardGoalsAggregator $goalsAggregator,
        private readonly AliasResolutionService $aliasResolution,
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
     *     hub: array{
     *         credit_cards: array{active_count: int, period_spend: string},
     *         loans: array{
     *             open_count: int,
     *             remaining_total: string,
     *             overdue_count: int,
     *             debtors_with_open: int
     *         }
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
            'hub' => $this->hub($user, $queryFilters),
            'series' => $this->series($user, $queryFilters, $groupBy),
            'by_category' => $this->byCategory($user, $queryFilters),
            'by_alias' => $this->byAlias($user, $queryFilters),
            'goals' => $this->goalsAggregator->forUser($user),
        ];
    }

    /**
     * Resumos de cartões / cobranças para o topo do dashboard (independentes dos filtros de entidade).
     *
     * @param  AnalyticsFilters  $filters
     * @return array{
     *     credit_cards: array{active_count: int, period_spend: string},
     *     loans: array{
     *         open_count: int,
     *         remaining_total: string,
     *         overdue_count: int,
     *         debtors_with_open: int
     *     }
     * }
     */
    public function hub(User $user, array $filters = []): array
    {
        $dateFilters = Arr::only($filters, ['from', 'to']);

        $periodSpend = (float) ($this->transactions->baseForUser($user, $dateFilters)
            ->toBase()
            ->whereNotNull('transactions.credit_card_id')
            ->where('transactions.type', 'debit')
            ->selectRaw('COALESCE(SUM(transactions.amount), 0) as total')
            ->value('total') ?? 0);

        $activeCards = CreditCard::query()
            ->forUser($user)
            ->where('is_active', true)
            ->count();

        $openStatuses = [LoanStatus::Open->value, LoanStatus::Partial->value];

        $openLoans = Loan::query()
            ->forUser($user)
            ->whereIn('status', $openStatuses)
            ->get(['id', 'amount', 'paid_amount', 'due_on', 'status', 'debtor_id']);

        $remaining = 0.0;
        $overdue = 0;
        $debtorIds = [];

        foreach ($openLoans as $loan) {
            $remaining += $loan->remainingAmount();
            if ($loan->isOverdue()) {
                $overdue++;
            }
            if ($loan->debtor_id !== null) {
                $debtorIds[(int) $loan->debtor_id] = true;
            }
        }

        return [
            'credit_cards' => [
                'active_count' => $activeCards,
                'period_spend' => $this->money($periodSpend),
            ],
            'loans' => [
                'open_count' => $openLoans->count(),
                'remaining_total' => $this->money($remaining),
                'overdue_count' => $overdue,
                'debtors_with_open' => count($debtorIds),
            ],
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
        $driver = $this->transactions->baseForUser($user, $filters)->getConnection()->getDriverName();
        $periodExpr = $this->periodExpression($driver, $groupBy);

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
     * Totals by category. Respects `type` filter; when omitted, returns credit and debit as separate rows.
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
        $groupByType = ($filters['type'] ?? null) === null || $filters['type'] === '';

        $select = '
                transactions.category_id as category_id,
                categories.name as name,
                categories.color as color,
                COALESCE(SUM(transactions.amount), 0) as total,
                COUNT(*) as count
            ';
        if ($groupByType) {
            $select = '
                transactions.category_id as category_id,
                categories.name as name,
                categories.color as color,
                transactions.type as type,
                COALESCE(SUM(transactions.amount), 0) as total,
                COUNT(*) as count
            ';
        }

        $query = $this->transactions->baseForUser($user, $filters)
            ->toBase()
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->selectRaw($select);

        if ($groupByType) {
            $query->groupBy(
                'transactions.category_id',
                'categories.name',
                'categories.color',
                'transactions.type'
            );
        } else {
            $query->groupBy('transactions.category_id', 'categories.name', 'categories.color');
        }

        $rows = $query->orderByDesc('total')->get();

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'category_id' => $row->category_id !== null ? (int) $row->category_id : null,
                'name' => $row->name !== null ? (string) $row->name : null,
                'color' => $row->color !== null ? (string) $row->color : null,
                'total' => $this->money((float) $row->total),
                'count' => (int) $row->count,
                'type' => $groupByType
                    ? (string) $row->type
                    : (string) $filters['type'],
            ];
        }

        return $items;
    }

    /**
     * Totals grouped by alias display_name (identical names merge into one bar per type).
     *
     * Prefers `raw_payload.alias_id` (applied on upload / retroactive). Falls back to
     * live AliasResolutionService match on original_description / description so
     * active rules appear in the chart before apply_to_existing runs.
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
        $groupByType = ($filters['type'] ?? null) === null || $filters['type'] === '';
        $effectiveType = $groupByType ? null : (string) $filters['type'];

        $aliasNames = TransactionAlias::query()
            ->forUser($user)
            ->get(['id', 'display_name'])
            ->keyBy('id');

        if ($aliasNames->isEmpty()) {
            return [];
        }

        $transactions = $this->transactions->baseForUser($user, $filters)
            ->get(['amount', 'type', 'description', 'raw_payload']);

        /** @var array<string, array{alias_id: int, name: string, type: string, total: float, count: int}> $buckets */
        $buckets = [];

        foreach ($transactions as $transaction) {
            $payload = is_array($transaction->raw_payload) ? $transaction->raw_payload : [];
            $aliasId = isset($payload['alias_id']) ? (int) $payload['alias_id'] : null;
            $name = null;

            if ($aliasId !== null && $aliasId > 0) {
                $alias = $aliasNames->get($aliasId);
                if ($alias !== null) {
                    $name = trim((string) $alias->display_name);
                }
            }

            if ($name === null || $name === '') {
                $rawDescription = isset($payload['original_description'])
                    && is_string($payload['original_description'])
                    && $payload['original_description'] !== ''
                    ? $payload['original_description']
                    : (string) $transaction->description;

                $match = $this->aliasResolution->resolve($user, $rawDescription);
                if ($match === null) {
                    continue;
                }

                $aliasId = $match->aliasId;
                $name = trim($match->displayName);
            }

            if ($name === '') {
                continue;
            }

            $rowType = (string) $transaction->type;
            $key = mb_strtolower($name).($groupByType ? '|'.$rowType : '');
            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'alias_id' => $aliasId,
                    'name' => $name,
                    'type' => $groupByType ? $rowType : (string) $effectiveType,
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
                'type' => $bucket['type'],
            ];
            $index++;
        }

        return $items;
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    /**
     * @param  'day'|'month'  $groupBy
     */
    private function periodExpression(string $driver, string $groupBy): string
    {
        if ($driver === 'sqlite') {
            return $groupBy === 'day'
                ? "strftime('%Y-%m-%d', transactions.occurred_on)"
                : "strftime('%Y-%m', transactions.occurred_on)";
        }

        return $groupBy === 'day'
            ? "DATE_FORMAT(transactions.occurred_on, '%Y-%m-%d')"
            : "DATE_FORMAT(transactions.occurred_on, '%Y-%m')";
    }
}

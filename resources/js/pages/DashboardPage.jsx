import { useCallback, useState } from 'react';
import { Plus } from 'lucide-react';
import AliasChart from '../components/dashboard/AliasChart';
import CategoryChart from '../components/dashboard/CategoryChart';
import EvolutionChart from '../components/dashboard/EvolutionChart';
import FilterBar from '../components/dashboard/FilterBar';
import GoalsWidget from '../components/dashboard/GoalsWidget';
import MetricCards from '../components/dashboard/MetricCards';
import Pagination from '../components/dashboard/Pagination';
import TransactionsTable from '../components/dashboard/TransactionsTable';
import DeleteTransactionDialog from '../components/transactions/DeleteTransactionDialog';
import RememberAliasDialog from '../components/transactions/RememberAliasDialog';
import TransactionFormModal from '../components/transactions/TransactionFormModal';
import GoalFormModal from '../components/goals/GoalFormModal';
import ErrorState from '../components/ui/ErrorState';
import Button from '../components/ui/Button';
import PageHeader from '../components/layout/PageHeader';
import { useAuth } from '../hooks/useAuth';
import { useCategories } from '../hooks/useCategories';
import { useDashboardAnalytics } from '../hooks/useDashboardAnalytics';
import { useDashboardFilters } from '../hooks/useDashboardFilters';
import { useDocumentTitle } from '../hooks/useDocumentTitle';
import { useCreateGoal } from '../hooks/useGoalMutations';
import {
    useCreateTransaction,
    useDeleteTransaction,
    useRememberAlias,
    useUpdateTransaction,
} from '../hooks/useTransactionMutations';
import { useTransactions } from '../hooks/useTransactions';
import { ABILITIES, can } from '../lib/auth';
import { maxDateRangeDaysFor } from '../lib/dates';

/**
 * DashboardPage — Etapa D §4.8 / §5.3.4 / §5.5 / PLAN_EXPANSAO §8.2–§8.5.
 */
export default function DashboardPage() {
    useDocumentTitle('Dashboard · Aura');

    const { user } = useAuth();
    const canManageTransactions = can(user, ABILITIES.transactionsManage);
    const canManageAliases = can(user, ABILITIES.aliasesManage);
    const canManageGoals = can(user, ABILITIES.goalsManage);
    const maxDateRangeDays = maxDateRangeDaysFor(user);

    const {
        filters,
        apiFilters,
        periodPreset,
        setPeriodPreset,
        setCustomRange,
        setFilters,
        setPage,
    } = useDashboardFilters();
    const analytics = useDashboardAnalytics(apiFilters);
    const transactions = useTransactions(apiFilters);
    const categories = useCategories();

    const [formState, setFormState] = useState(null);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [rememberTarget, setRememberTarget] = useState(null);
    const [goalFormOpen, setGoalFormOpen] = useState(false);

    const refetchTransactions = transactions.refetch;
    const refetchAnalytics = analytics.refetch;

    const refreshDashboard = useCallback(async () => {
        await Promise.all([refetchTransactions(), refetchAnalytics()]);
    }, [refetchTransactions, refetchAnalytics]);

    const createTx = useCreateTransaction({ onSuccess: refreshDashboard });
    const updateTx = useUpdateTransaction({ onSuccess: refreshDashboard });
    const deleteTx = useDeleteTransaction({ onSuccess: refreshDashboard });
    const remember = useRememberAlias();
    const createGoal = useCreateGoal({ onSuccess: refreshDashboard });

    const chartsLoading = analytics.status === 'loading';
    const analyticsError =
        analytics.status === 'error' ? analytics.error || 'Tente novamente em instantes.' : null;
    const isRefreshing =
        (analytics.status === 'loading' && Boolean(analytics.data)) ||
        (transactions.status === 'loading' && transactions.data.length > 0);

    const hasSearchQuery = Boolean(filters.q && filters.q.trim());
    const hasTypeOrCategoryFilter = Boolean(filters.type || filters.category_id);
    const looksEmptyAccount =
        !hasSearchQuery &&
        !hasTypeOrCategoryFilter &&
        (transactions.meta?.total ?? 0) === 0 &&
        (analytics.data?.cards?.transactions_count ?? 0) === 0;

    const formOpen = formState !== null;
    const formMode = formState?.mode === 'edit' ? 'edit' : 'create';
    const formSubmitting =
        formMode === 'edit' ? updateTx.isLoading : createTx.isLoading;

    return (
        <div className="flex flex-col gap-8 md:gap-10">
            <PageHeader
                title="Visão geral"
                description="Acompanhe entradas, saídas e tendências do período."
                actions={
                    canManageTransactions ? (
                        <Button
                            type="button"
                            size="sm"
                            onClick={() => setFormState({ mode: 'create' })}
                        >
                            <Plus size={16} strokeWidth={2} aria-hidden />
                            Nova transação
                        </Button>
                    ) : null
                }
            />

            <FilterBar
                periodPreset={periodPreset}
                from={filters.from}
                to={filters.to}
                maxDateRangeDays={maxDateRangeDays}
                onPeriodChange={setPeriodPreset}
                onCustomRange={setCustomRange}
                type={filters.type}
                onTypeChange={(type) => setFilters({ type })}
                categoryId={filters.category_id}
                onCategoryChange={(category_id) => setFilters({ category_id })}
                categories={categories.data}
                categoriesLoading={categories.status === 'loading'}
                q={filters.q}
                onSearchChange={(q) => setFilters({ q })}
                refreshing={isRefreshing}
            />

            <section aria-label="Métricas do período">
                {analyticsError && !analytics.data?.cards ? (
                    <ErrorState
                        title="Não foi possível carregar as métricas"
                        message={analyticsError}
                        onRetry={analytics.refetch}
                    />
                ) : (
                    <MetricCards
                        cards={analytics.data?.cards}
                        loading={chartsLoading}
                    />
                )}
            </section>

            <section
                className={
                    canManageGoals
                        ? 'grid grid-cols-1 gap-5 lg:grid-cols-6'
                        : 'grid grid-cols-1 gap-5 lg:grid-cols-2'
                }
                aria-label="Gráficos"
            >
                <EvolutionChart
                    className={canManageGoals ? 'min-w-0 lg:col-span-4' : 'min-w-0'}
                    series={analytics.data?.series}
                    loading={chartsLoading}
                    error={analyticsError}
                    onRetry={analytics.refetch}
                />
                {canManageGoals ? (
                    <GoalsWidget
                        className="min-w-0 lg:col-span-2"
                        goals={analytics.data?.goals}
                        loading={chartsLoading}
                        onCreate={() => setGoalFormOpen(true)}
                    />
                ) : null}
                <CategoryChart
                    className={canManageGoals ? 'min-w-0 lg:col-span-3' : 'min-w-0'}
                    byCategory={analytics.data?.by_category}
                    filterType={filters.type || ''}
                    loading={chartsLoading}
                    error={analyticsError}
                    onRetry={analytics.refetch}
                />
                <AliasChart
                    className={canManageGoals ? 'min-w-0 lg:col-span-3' : 'min-w-0'}
                    byAlias={analytics.data?.by_alias}
                    filterType={filters.type || ''}
                    loading={chartsLoading}
                    error={analyticsError}
                    onRetry={analytics.refetch}
                />
            </section>

            <section className="flex flex-col gap-3" aria-label="Movimentações">
                <h2 className="text-h2 font-semibold text-ink">Movimentações</h2>
                <TransactionsTable
                    rows={transactions.data}
                    sort={filters.sort}
                    direction={filters.direction}
                    onSortChange={({ sort, direction }) => setFilters({ sort, direction })}
                    status={transactions.status}
                    error={transactions.error}
                    onRetry={transactions.refetch}
                    hasTypeOrCategoryFilter={hasTypeOrCategoryFilter}
                    hasSearchQuery={hasSearchQuery}
                    looksEmptyAccount={looksEmptyAccount}
                    canRememberAlias={canManageAliases}
                    onEdit={
                        canManageTransactions
                            ? (row) => setFormState({ mode: 'edit', transaction: row })
                            : undefined
                    }
                    onDelete={
                        canManageTransactions
                            ? (row) => setDeleteTarget(row)
                            : undefined
                    }
                    onRememberAlias={
                        canManageAliases
                            ? (row) => setRememberTarget(row)
                            : undefined
                    }
                />
                {transactions.status !== 'error' ? (
                    <Pagination meta={transactions.meta} onPageChange={setPage} />
                ) : null}
            </section>

            <TransactionFormModal
                open={formOpen}
                mode={formMode}
                transaction={formState?.transaction ?? null}
                categories={categories.data}
                categoriesLoading={categories.status === 'loading'}
                onCategoryCreated={() => {
                    categories.refresh?.();
                }}
                submitting={formSubmitting}
                onClose={() => {
                    if (!formSubmitting) {
                        setFormState(null);
                    }
                }}
                onSubmit={async (payload) => {
                    if (formMode === 'edit' && formState?.transaction?.id != null) {
                        await updateTx.mutate(formState.transaction.id, payload);
                    } else {
                        await createTx.mutate(payload);
                    }
                    setFormState(null);
                }}
            />

            <DeleteTransactionDialog
                open={deleteTarget !== null}
                transaction={deleteTarget}
                submitting={deleteTx.isLoading}
                onClose={() => {
                    if (!deleteTx.isLoading) {
                        setDeleteTarget(null);
                    }
                }}
                onConfirm={async () => {
                    if (!deleteTarget?.id) {
                        return;
                    }
                    await deleteTx.mutate(deleteTarget.id);
                    setDeleteTarget(null);
                }}
            />

            <RememberAliasDialog
                open={rememberTarget !== null}
                transaction={rememberTarget}
                submitting={remember.isLoading}
                onClose={() => {
                    if (!remember.isLoading) {
                        setRememberTarget(null);
                    }
                }}
                onSubmit={async (payload) => {
                    if (!rememberTarget?.id) {
                        return;
                    }
                    await remember.mutate(rememberTarget.id, payload);
                    setRememberTarget(null);
                }}
            />

            {canManageGoals ? (
                <GoalFormModal
                    open={goalFormOpen}
                    mode="create"
                    categories={categories.data}
                    categoriesLoading={categories.status === 'loading'}
                    submitting={createGoal.isLoading}
                    onClose={() => {
                        if (!createGoal.isLoading) {
                            setGoalFormOpen(false);
                        }
                    }}
                    onSubmit={async (payload) => {
                        await createGoal.mutate(payload);
                        setGoalFormOpen(false);
                    }}
                />
            ) : null}
        </div>
    );
}

import { useCallback, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { ArrowRight, Plus } from 'lucide-react';
import AliasChart from '../components/dashboard/AliasChart';
import CategoryChart from '../components/dashboard/CategoryChart';
import EvolutionChart from '../components/dashboard/EvolutionChart';
import FilterBar from '../components/dashboard/FilterBar';
import GoalsWidget from '../components/dashboard/GoalsWidget';
import HubMetricCards from '../components/dashboard/HubMetricCards';
import MetricCards from '../components/dashboard/MetricCards';
import TransactionsWidget from '../components/dashboard/TransactionsWidget';
import GoalFormModal from '../components/goals/GoalFormModal';
import ErrorState from '../components/ui/ErrorState';
import Button from '../components/ui/Button';
import PageHeader from '../components/layout/PageHeader';
import { useAuth } from '../hooks/useAuth';
import { useCategories } from '../hooks/useCategories';
import { useCreditCards } from '../hooks/useCreditCards';
import { useDashboardAnalytics } from '../hooks/useDashboardAnalytics';
import { useDashboardFilters } from '../hooks/useDashboardFilters';
import { useDebtors } from '../hooks/useDebtors';
import { useDocumentTitle } from '../hooks/useDocumentTitle';
import { useCreateGoal } from '../hooks/useGoalMutations';
import { useTransactions } from '../hooks/useTransactions';
import { ABILITIES, can, featureEnabled } from '../lib/auth';
import { maxDateRangeDaysFor } from '../lib/dates';

const PREVIEW_PER_PAGE = 6;

/**
 * DashboardPage — métricas, gráficos e resumo de movimentações.
 * Listagem completa: `/transactions`.
 */
export default function DashboardPage() {
    useDocumentTitle('Dashboard · Aura');

    const navigate = useNavigate();
    const { user } = useAuth();
    const canManageTransactions = can(user, ABILITIES.transactionsManage);
    const canManageGoals = can(user, ABILITIES.goalsManage);
    const showCreditCards = featureEnabled(user, 'credit_cards');
    const showLoans = featureEnabled(user, 'loans');
    const maxDateRangeDays = maxDateRangeDaysFor(user);

    const {
        filters,
        apiFilters,
        periodPreset,
        setPeriodPreset,
        setCustomRange,
        setFilters,
    } = useDashboardFilters();
    const analytics = useDashboardAnalytics(apiFilters);
    const previewFilters = useMemo(
        () => ({
            ...apiFilters,
            page: 1,
            per_page: PREVIEW_PER_PAGE,
        }),
        [apiFilters],
    );
    const transactions = useTransactions(previewFilters);
    const categories = useCategories();
    const creditCards = useCreditCards({
        is_active: 1,
        per_page: 100,
        enabled: showCreditCards,
    });
    const debtors = useDebtors({
        per_page: 100,
        enabled: showLoans,
    });

    const [goalFormOpen, setGoalFormOpen] = useState(false);

    const refetchAnalytics = analytics.refetch;
    const createGoal = useCreateGoal({
        onSuccess: useCallback(async () => {
            await refetchAnalytics();
        }, [refetchAnalytics]),
    });

    const chartsLoading = analytics.status === 'loading';
    const analyticsError =
        analytics.status === 'error' ? analytics.error || 'Tente novamente em instantes.' : null;
    const isRefreshing =
        (analytics.status === 'loading' && Boolean(analytics.data)) ||
        (transactions.status === 'loading' && transactions.data.length > 0);

    function openCreateTransaction() {
        navigate('/transactions', { state: { openCreate: true } });
    }

    return (
        <div className="flex flex-col gap-8 md:gap-10">
            <PageHeader
                title="Visão geral"
                description="Acompanhe entradas, saídas e tendências do período."
                actions={
                    <div className="flex flex-wrap items-center gap-2">
                        <Link
                            to="/transactions"
                            className="inline-flex h-9 items-center gap-1 rounded-full px-3 text-caption font-medium text-ink-secondary no-underline transition hover:bg-surface-raised hover:text-ink"
                        >
                            Movimentações
                            <ArrowRight size={14} strokeWidth={2} aria-hidden />
                        </Link>
                        {canManageTransactions ? (
                            <Button
                                type="button"
                                size="sm"
                                onClick={openCreateTransaction}
                            >
                                <Plus size={16} strokeWidth={2} aria-hidden />
                                Nova transação
                            </Button>
                        ) : null}
                    </div>
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
                showCreditCardFilter={showCreditCards}
                creditCardId={filters.credit_card_id}
                onCreditCardChange={(credit_card_id) => setFilters({ credit_card_id })}
                creditCards={creditCards.data}
                creditCardsLoading={creditCards.status === 'loading'}
                showDebtorFilter={showLoans}
                debtorId={filters.debtor_id}
                onDebtorChange={(debtor_id) => setFilters({ debtor_id })}
                debtors={debtors.data}
                debtorsLoading={debtors.status === 'loading'}
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

            {(showCreditCards || showLoans) &&
            !(analyticsError && !analytics.data?.hub) ? (
                <section aria-label="Resumos de cartões e cobranças">
                    <HubMetricCards
                        hub={analytics.data?.hub}
                        showCards={showCreditCards}
                        showLoans={showLoans}
                        loading={chartsLoading}
                    />
                </section>
            ) : null}

            <section aria-label="Movimentações recentes">
                <TransactionsWidget
                    rows={transactions.data}
                    total={transactions.meta?.total ?? transactions.data.length}
                    loading={transactions.status === 'loading'}
                    error={
                        transactions.status === 'error'
                            ? transactions.error || 'Tente novamente em instantes.'
                            : null
                    }
                    onRetry={transactions.refetch}
                    onCreate={canManageTransactions ? openCreateTransaction : undefined}
                />
            </section>

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

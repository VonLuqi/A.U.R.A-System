import CategoryChart from '../components/dashboard/CategoryChart';
import EvolutionChart from '../components/dashboard/EvolutionChart';
import FilterBar from '../components/dashboard/FilterBar';
import MetricCards from '../components/dashboard/MetricCards';
import Pagination from '../components/dashboard/Pagination';
import TransactionsTable from '../components/dashboard/TransactionsTable';
import ErrorState from '../components/ui/ErrorState';
import PageHeader from '../components/layout/PageHeader';
import { useCategories } from '../hooks/useCategories';
import { useDashboardAnalytics } from '../hooks/useDashboardAnalytics';
import { useDashboardFilters } from '../hooks/useDashboardFilters';
import { useDocumentTitle } from '../hooks/useDocumentTitle';
import { useTransactions } from '../hooks/useTransactions';

/**
 * DashboardPage — Etapa D §4.8 / §5.3.4 / §5.5.
 */
export default function DashboardPage() {
    useDocumentTitle('Dashboard · Aura');

    const { filters, apiFilters, periodPreset, setPeriodPreset, setFilters, setPage } =
        useDashboardFilters();
    const analytics = useDashboardAnalytics(apiFilters);
    const transactions = useTransactions(apiFilters);
    const categories = useCategories();
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

    return (
        <div className="flex flex-col gap-8 md:gap-10">
            <PageHeader
                title="Visão geral"
                description="Acompanhe entradas, saídas e tendências do período."
            />

            <FilterBar
                periodPreset={periodPreset}
                onPeriodChange={setPeriodPreset}
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
                className="grid grid-cols-1 gap-5 lg:grid-cols-2"
                aria-label="Gráficos"
            >
                <EvolutionChart
                    className="min-w-0"
                    series={analytics.data?.series}
                    loading={chartsLoading}
                    error={analyticsError}
                    onRetry={analytics.refetch}
                />
                <CategoryChart
                    className="min-w-0"
                    byCategory={analytics.data?.by_category}
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
                />
                {transactions.status !== 'error' ? (
                    <Pagination meta={transactions.meta} onPageChange={setPage} />
                ) : null}
            </section>
        </div>
    );
}

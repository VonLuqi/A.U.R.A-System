import { useCallback, useEffect, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { Plus } from 'lucide-react';
import FilterBar from '../components/dashboard/FilterBar';
import Pagination from '../components/dashboard/Pagination';
import TransactionsTable from '../components/dashboard/TransactionsTable';
import InstallmentPlansPanel from '../components/loans/InstallmentPlansPanel';
import DeleteTransactionDialog from '../components/transactions/DeleteTransactionDialog';
import RememberAliasDialog from '../components/transactions/RememberAliasDialog';
import TransactionFormModal from '../components/transactions/TransactionFormModal';
import Button from '../components/ui/Button';
import PageHeader from '../components/layout/PageHeader';
import Pill from '../components/ui/Pill';
import { useAuth } from '../hooks/useAuth';
import { useCategories } from '../hooks/useCategories';
import { useCreditCards } from '../hooks/useCreditCards';
import { useDashboardFilters } from '../hooks/useDashboardFilters';
import { useDebtors } from '../hooks/useDebtors';
import { useDocumentTitle } from '../hooks/useDocumentTitle';
import {
    useCreateTransaction,
    useDeleteTransaction,
    useRememberAlias,
    useUpdateTransaction,
} from '../hooks/useTransactionMutations';
import { useTransactions } from '../hooks/useTransactions';
import { ABILITIES, can, featureEnabled } from '../lib/auth';
import { maxDateRangeDaysFor } from '../lib/dates';

const VIEW_TABS = [
    { id: 'movements', label: 'Lista' },
    { id: 'plans', label: 'Parcelamentos' },
];

/**
 * TransactionsPage — listagem e CRUD de movimentações + parcelamentos.
 */
export default function TransactionsPage() {
    useDocumentTitle('Movimentações · Aura');

    const navigate = useNavigate();
    const location = useLocation();
    const { user } = useAuth();
    const canManageTransactions = can(user, ABILITIES.transactionsManage);
    const canManageAliases = can(user, ABILITIES.aliasesManage);
    const showCreditCards = featureEnabled(user, 'credit_cards');
    const showLoans = featureEnabled(user, 'loans');
    const maxDateRangeDays = maxDateRangeDaysFor(user);

    const [view, setView] = useState(/** @type {'movements'|'plans'} */ ('movements'));
    const [openPlanId, setOpenPlanId] = useState(/** @type {number|null} */ (null));

    const categories = useCategories();
    const creditCards = useCreditCards({
        is_active: 1,
        per_page: 100,
        enabled: showCreditCards || showLoans,
    });
    const debtors = useDebtors({
        per_page: 100,
        enabled: showLoans,
    });
    const {
        filters,
        apiFilters,
        periodPreset,
        setPeriodPreset,
        setCycleOffset,
        setCustomRange,
        setFilters,
        setPage,
    } = useDashboardFilters({ creditCards: creditCards.data });
    const transactions = useTransactions(apiFilters);

    const [formState, setFormState] = useState(null);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [rememberTarget, setRememberTarget] = useState(null);

    useEffect(() => {
        if (!location.state?.openCreate || !canManageTransactions) {
            return;
        }

        setView('movements');
        setFormState({ mode: 'create' });
        navigate(`${location.pathname}${location.search}`, { replace: true, state: {} });
    }, [
        canManageTransactions,
        location.pathname,
        location.search,
        location.state,
        navigate,
    ]);

    useEffect(() => {
        const planId = location.state?.openInstallmentPlanId;
        if (planId == null || !showLoans) {
            return;
        }

        setView('plans');
        setOpenPlanId(Number(planId));
        navigate(`${location.pathname}${location.search}`, { replace: true, state: {} });
    }, [
        location.pathname,
        location.search,
        location.state,
        navigate,
        showLoans,
    ]);

    const refresh = useCallback(async () => {
        await transactions.refetch();
    }, [transactions.refetch]);

    const createTx = useCreateTransaction({ onSuccess: refresh });
    const updateTx = useUpdateTransaction({ onSuccess: refresh });
    const deleteTx = useDeleteTransaction({ onSuccess: refresh });
    const remember = useRememberAlias();

    const isRefreshing =
        transactions.status === 'loading' && transactions.data.length > 0;

    const hasSearchQuery = Boolean(filters.q && filters.q.trim());
    const hasTypeOrCategoryFilter = Boolean(
        filters.type ||
            filters.category_id ||
            filters.credit_card_id ||
            filters.debtor_id,
    );
    const looksEmptyAccount =
        !hasSearchQuery &&
        !hasTypeOrCategoryFilter &&
        (transactions.meta?.total ?? 0) === 0;

    const formOpen = formState !== null;
    const formMode = formState?.mode === 'edit' ? 'edit' : 'create';
    const formSubmitting =
        formMode === 'edit' ? updateTx.isLoading : createTx.isLoading;

    return (
        <div className="flex flex-col gap-8 md:gap-10">
            <PageHeader
                title="Movimentações"
                description={
                    view === 'plans'
                        ? 'Séries de parcelas do cartão ou cadastro manual. Pessoa opcional para cobrança.'
                        : 'Entradas e saídas do período, com filtros e vínculos.'
                }
                actions={
                    view === 'movements' && canManageTransactions ? (
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

            {showLoans ? (
                <div
                    className="flex flex-wrap items-center gap-2"
                    role="tablist"
                    aria-label="Seções de movimentações"
                >
                    {VIEW_TABS.map((tab) => (
                        <Pill
                            key={tab.id}
                            active={view === tab.id}
                            onClick={() => setView(tab.id)}
                        >
                            {tab.label}
                        </Pill>
                    ))}
                </div>
            ) : null}

            {view === 'plans' && showLoans ? (
                <InstallmentPlansPanel
                    creditCards={creditCards.data}
                    debtors={debtors.data}
                    openPlanId={openPlanId}
                    onOpenPlanConsumed={() => setOpenPlanId(null)}
                />
            ) : (
                <>
                    <FilterBar
                        periodPreset={periodPreset}
                        from={filters.from}
                        to={filters.to}
                        cycleOffset={filters.cycle_offset}
                        maxDateRangeDays={maxDateRangeDays}
                        onPeriodChange={setPeriodPreset}
                        onCycleOffsetChange={setCycleOffset}
                        onCustomRange={setCustomRange}
                        type={filters.type}
                        onTypeChange={(type) => setFilters({ type })}
                        categoryId={filters.category_id}
                        onCategoryChange={(category_id) => setFilters({ category_id })}
                        categories={categories.data}
                        categoriesLoading={categories.status === 'loading'}
                        showCreditCardFilter={showCreditCards}
                        creditCardId={filters.credit_card_id}
                        onCreditCardChange={(credit_card_id) =>
                            setFilters({ credit_card_id })
                        }
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

                    <section className="flex flex-col gap-3" aria-label="Lista de movimentações">
                        <TransactionsTable
                            rows={transactions.data}
                            sort={filters.sort}
                            direction={filters.direction}
                            onSortChange={({ sort, direction }) =>
                                setFilters({ sort, direction })
                            }
                            status={transactions.status}
                            error={transactions.error}
                            onRetry={transactions.refetch}
                            hasTypeOrCategoryFilter={hasTypeOrCategoryFilter}
                            hasSearchQuery={hasSearchQuery}
                            looksEmptyAccount={looksEmptyAccount}
                            canRememberAlias={canManageAliases}
                            onEdit={
                                canManageTransactions
                                    ? (row) =>
                                          setFormState({ mode: 'edit', transaction: row })
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
                </>
            )}

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
        </div>
    );
}

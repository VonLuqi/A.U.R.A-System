import { useCallback, useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
    ArrowLeft,
    Ban,
    Banknote,
    ChevronRight,
    Link2,
    ListFilter,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import CancelLoanDialog from '../components/loans/CancelLoanDialog';
import DebtorFormModal from '../components/loans/DebtorFormModal';
import DeleteDebtorDialog from '../components/loans/DeleteDebtorDialog';
import DeleteLoanDialog from '../components/loans/DeleteLoanDialog';
import LinkDebtorTransactionsModal from '../components/loans/LinkDebtorTransactionsModal';
import LoanFormModal from '../components/loans/LoanFormModal';
import MarkLoanPaidDialog from '../components/loans/MarkLoanPaidDialog';
import PageHeader from '../components/layout/PageHeader';
import Badge from '../components/ui/Badge';
import Button from '../components/ui/Button';
import EmptyState from '../components/ui/EmptyState';
import ErrorState from '../components/ui/ErrorState';
import Input from '../components/ui/Input';
import Modal from '../components/ui/Modal';
import Pill from '../components/ui/Pill';
import Skeleton from '../components/ui/Skeleton';
import { useCreditCards } from '../hooks/useCreditCards';
import { useCategories } from '../hooks/useCategories';
import { useDebtors } from '../hooks/useDebtors';
import {
    useCreateDebtor,
    useDeleteDebtor,
    useUpdateDebtor,
} from '../hooks/useDebtorMutations';
import { useDocumentTitle } from '../hooks/useDocumentTitle';
import { useLoans } from '../hooks/useLoans';
import {
    useCancelLoan,
    useCreateLoan,
    useDeleteLoan,
    useMarkLoanPaid,
    useUpdateLoan,
} from '../hooks/useLoanMutations';
import { cx } from '../lib/cx';
import { formatDate, formatMoney } from '../lib/format';
import {
    isLoanOverdue,
    loanKindLabel,
    loanStatusLabel,
    LOAN_KINDS,
    LOAN_KIND_LABELS,
    LOAN_STATUSES,
} from '../lib/loans';

const STATUS_FILTERS = [
    { id: '', label: 'Todas' },
    { id: 'open', label: 'Em aberto' },
    { id: 'paid', label: 'Pagas' },
    { id: 'overdue', label: 'Atrasadas' },
];

const KIND_FILTERS = [
    { id: '', label: 'Todos' },
    { id: LOAN_KINDS.cash, label: LOAN_KIND_LABELS.cash },
    { id: LOAN_KINDS.card_limit, label: LOAN_KIND_LABELS.card_limit },
];

const VIEW_TABS = [
    { id: 'people', label: 'Pessoas' },
    { id: 'loans', label: 'Empréstimos' },
];

/**
 * LoansPage — pessoas em cards (detalhe ao clicar) + aba Empréstimos.
 */
export default function LoansPage() {
    useDocumentTitle('Devedores · Aura');
    const navigate = useNavigate();

    const [view, setView] = useState(/** @type {'people'|'loans'} */ ('people'));
    const [selectedDebtor, setSelectedDebtor] = useState(null);
    const [qInput, setQInput] = useState('');
    const [q, setQ] = useState('');
    const [peopleQInput, setPeopleQInput] = useState('');
    const [peopleQ, setPeopleQ] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [kindFilter, setKindFilter] = useState('');
    const [page, setPage] = useState(1);
    const [filtersOpen, setFiltersOpen] = useState(false);

    const [formState, setFormState] = useState(null);
    const [debtorForm, setDebtorForm] = useState(null);
    const [deleteDebtorTarget, setDeleteDebtorTarget] = useState(null);
    const [linkDebtorTarget, setLinkDebtorTarget] = useState(null);
    const [markPaidTarget, setMarkPaidTarget] = useState(null);
    const [cancelTarget, setCancelTarget] = useState(null);
    const [deleteTarget, setDeleteTarget] = useState(null);

    const creditCards = useCreditCards({ is_active: 1, per_page: 100 });
    const categories = useCategories();
    const debtors = useDebtors({
        per_page: 100,
        q: view === 'people' && !selectedDebtor && peopleQ ? peopleQ : undefined,
    });

    const loansFilters = useMemo(() => {
        /** @type {Record<string, string|number|boolean>} */
        const params = { page, per_page: 50 };

        if (q) {
            params.q = q;
        }

        if (statusFilter === 'overdue') {
            params.overdue = 1;
        } else if (statusFilter === 'open' || statusFilter === 'paid') {
            params.status = statusFilter;
        }

        if (kindFilter) {
            params.kind = kindFilter;
        }

        return params;
    }, [q, statusFilter, kindFilter, page]);

    const detailFilters = useMemo(
        () => ({
            debtor_id: selectedDebtor?.id,
            per_page: 100,
            enabled: Boolean(selectedDebtor?.id),
        }),
        [selectedDebtor?.id],
    );

    const loans = useLoans(
        view === 'loans' && !selectedDebtor
            ? loansFilters
            : { enabled: false },
    );
    const detailLoans = useLoans(detailFilters);

    const refreshDebtors = useCallback(async () => {
        await debtors.refetch();
    }, [debtors.refetch]);

    const refreshLoans = useCallback(async () => {
        await Promise.all([
            loans.refetch(),
            detailLoans.refetch(),
        ]);
    }, [loans.refetch, detailLoans.refetch]);

    const refreshAll = useCallback(async () => {
        await Promise.all([refreshDebtors(), refreshLoans()]);
    }, [refreshDebtors, refreshLoans]);

    const createDebtor = useCreateDebtor({ onSuccess: refreshDebtors });
    const updateDebtor = useUpdateDebtor({ onSuccess: refreshDebtors });
    const deleteDebtorMut = useDeleteDebtor({
        onSuccess: async () => {
            setSelectedDebtor(null);
            await refreshDebtors();
        },
    });

    const createLoan = useCreateLoan({ onSuccess: refreshAll });
    const updateLoan = useUpdateLoan({ onSuccess: refreshAll });
    const deleteLoan = useDeleteLoan({ onSuccess: refreshAll });
    const markPaid = useMarkLoanPaid({ onSuccess: refreshAll });
    const cancelLoan = useCancelLoan({ onSuccess: refreshAll });

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setQ(qInput.trim());
            setPage(1);
        }, 300);

        return () => window.clearTimeout(timer);
    }, [qInput]);

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setPeopleQ(peopleQInput.trim());
        }, 300);

        return () => window.clearTimeout(timer);
    }, [peopleQInput]);

    useEffect(() => {
        if (!selectedDebtor?.id) {
            return;
        }
        const fresh = debtors.data.find((d) => d.id === selectedDebtor.id);
        if (fresh) {
            setSelectedDebtor(fresh);
        }
    }, [debtors.data, selectedDebtor?.id]);

    // After expand splits shared loans, refresh people counts once per debtor open.
    useEffect(() => {
        if (!selectedDebtor?.id || detailLoans.status !== 'success') {
            return;
        }
        void refreshDebtors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selectedDebtor?.id, detailLoans.status, detailLoans.meta?.total]);

    function openInstallmentPlan(planId) {
        navigate('/transactions', {
            state: { openInstallmentPlanId: planId },
        });
    }

    const formOpen = formState !== null;
    const formMode = formState?.mode === 'edit' ? 'edit' : 'create';
    const formSubmitting =
        formMode === 'edit' ? updateLoan.isLoading : createLoan.isLoading;

    const rows = selectedDebtor ? detailLoans.data : loans.data;
    const meta = selectedDebtor ? detailLoans.meta : loans.meta;
    const listStatus = selectedDebtor ? detailLoans.status : loans.status;
    const listError = selectedDebtor ? detailLoans.error : loans.error;
    const listRefetch = selectedDebtor ? detailLoans.refetch : loans.refetch;
    const peopleRows = debtors.data;

    const remainingLabel =
        meta?.loans_remaining == null
            ? 'ilimitado'
            : `${meta.loans_remaining} restante${meta.loans_remaining === 1 ? '' : 's'}`;

    const activeFilterCount =
        (statusFilter !== '' ? 1 : 0) + (kindFilter !== '' ? 1 : 0);
    const activeStatusLabel =
        STATUS_FILTERS.find((option) => option.id === statusFilter)?.label ?? 'Todas';
    const activeKindLabel =
        KIND_FILTERS.find((option) => option.id === kindFilter)?.label ?? 'Todos';
    const activeFilterParts = [];
    if (statusFilter !== '') {
        activeFilterParts.push(activeStatusLabel);
    }
    if (kindFilter !== '') {
        activeFilterParts.push(activeKindLabel);
    }
    const activeFilterLabel =
        activeFilterParts.length === 0 ? 'Todos' : activeFilterParts.join(' · ');

    const statusPills = (
        <div className="flex flex-wrap items-center gap-2" role="group" aria-label="Status">
            {STATUS_FILTERS.map((option) => (
                <Pill
                    key={option.id || 'all-status'}
                    active={statusFilter === option.id}
                    onClick={() => {
                        setStatusFilter(option.id);
                        setPage(1);
                    }}
                >
                    {option.label}
                </Pill>
            ))}
        </div>
    );

    const kindPills = (
        <div className="flex flex-wrap items-center gap-2" role="group" aria-label="Tipo">
            {KIND_FILTERS.map((option) => (
                <Pill
                    key={option.id || 'all-kind'}
                    active={kindFilter === option.id}
                    onClick={() => {
                        setKindFilter(option.id);
                        setPage(1);
                    }}
                >
                    {option.label}
                </Pill>
            ))}
        </div>
    );

    function renderLoanList({ showDebtorColumn = true } = {}) {
        return (
            <>
                {listStatus === 'error' ? (
                    <ErrorState
                        title="Não foi possível carregar as cobranças"
                        message={listError || 'Tente novamente em instantes.'}
                        onRetry={listRefetch}
                    />
                ) : null}

                {listStatus === 'loading' && rows.length === 0 ? (
                    <div aria-busy="true" aria-label="Carregando cobranças">
                        <Skeleton.Table rows={5} />
                    </div>
                ) : null}

                {listStatus !== 'error' &&
                listStatus !== 'loading' &&
                rows.length === 0 ? (
                    <EmptyState
                        title={
                            selectedDebtor
                                ? 'Nenhuma cobrança para esta pessoa'
                                : 'Nada a cobrar no momento'
                        }
                        description={
                            selectedDebtor
                                ? 'Vincule saídas ou registre um empréstimo.'
                                : 'Cadastre pessoas e vincule saídas ou empréstimos.'
                        }
                        action={
                            selectedDebtor
                                ? {
                                      label: 'Vincular saídas',
                                      onClick: () => setLinkDebtorTarget(selectedDebtor),
                                  }
                                : {
                                      label: 'Ver pessoas',
                                      onClick: () => {
                                          setView('people');
                                          setSelectedDebtor(null);
                                      },
                                  }
                        }
                    />
                ) : null}

                {rows.length > 0 ? (
                    <div className="overflow-hidden rounded-xl border border-border-subtle bg-surface">
                        <ul
                            className={cx(
                                'md:hidden',
                                listStatus === 'loading' && 'opacity-60',
                            )}
                        >
                            {rows.map((row) => (
                                <LoanMobileRow
                                    key={row.id}
                                    row={row}
                                    onEdit={() => setFormState({ mode: 'edit', loan: row })}
                                    onMarkPaid={() => setMarkPaidTarget(row)}
                                    onCancel={() => setCancelTarget(row)}
                                    onDelete={() => setDeleteTarget(row)}
                                    onOpenPlan={
                                        row.installment_plan_id
                                            ? () =>
                                                  openInstallmentPlan(
                                                      row.installment_plan_id,
                                                  )
                                            : undefined
                                    }
                                />
                            ))}
                        </ul>

                        <div className="hidden overflow-x-auto md:block">
                            <table className="min-w-[64rem] w-full border-collapse text-body text-ink">
                                <thead>
                                    <tr className="border-b border-border-subtle text-left text-caption font-medium text-ink-secondary">
                                        {showDebtorColumn ? (
                                            <th className="px-4 py-3">Devedor</th>
                                        ) : (
                                            <th className="px-4 py-3">Descrição</th>
                                        )}
                                        <th className="px-4 py-3">Tipo</th>
                                        <th className="px-4 py-3">Valor</th>
                                        <th className="px-4 py-3">Restante</th>
                                        <th className="px-4 py-3">Cobrar em</th>
                                        <th className="px-4 py-3">Status</th>
                                        <th className="px-4 py-3">Cartão</th>
                                        <th className="px-4 py-3 text-right">
                                            <span className="sr-only">Ações</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    className={
                                        listStatus === 'loading' ? 'opacity-60' : undefined
                                    }
                                >
                                    {rows.map((row) => (
                                        <LoanDesktopRow
                                            key={row.id}
                                            row={row}
                                            showDebtor={showDebtorColumn}
                                            onEdit={() =>
                                                setFormState({ mode: 'edit', loan: row })
                                            }
                                            onMarkPaid={() => setMarkPaidTarget(row)}
                                            onCancel={() => setCancelTarget(row)}
                                            onDelete={() => setDeleteTarget(row)}
                                            onOpenPlan={
                                                row.installment_plan_id
                                                    ? () =>
                                                          openInstallmentPlan(
                                                              row.installment_plan_id,
                                                          )
                                                    : undefined
                                            }
                                        />
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ) : null}

                {!selectedDebtor && meta && meta.last_page > 1 ? (
                    <div className="flex items-center justify-center gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            disabled={page <= 1 || listStatus === 'loading'}
                            onClick={() => setPage((p) => Math.max(1, p - 1))}
                        >
                            Anterior
                        </Button>
                        <span className="text-caption text-ink-secondary">
                            Página {meta.current_page} de {meta.last_page}
                        </span>
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            disabled={
                                page >= meta.last_page || listStatus === 'loading'
                            }
                            onClick={() => setPage((p) => p + 1)}
                        >
                            Próxima
                        </Button>
                    </div>
                ) : null}
            </>
        );
    }

    return (
        <div className="flex flex-col gap-8 md:gap-10">
            <PageHeader
                title="Devedores"
                description="Pessoas e cobranças. Parcelamentos do cartão ficam em Movimentações."
                actions={(
                    <div className="flex flex-wrap items-center gap-2">
                        {!selectedDebtor && view === 'people' ? (
                            <Button
                                type="button"
                                size="sm"
                                variant="secondary"
                                onClick={() => setDebtorForm({ mode: 'create' })}
                            >
                                <Plus size={16} strokeWidth={2} aria-hidden />
                                Nova pessoa
                            </Button>
                        ) : null}
                        {selectedDebtor || view === 'loans' ? (
                            <Button
                                type="button"
                                size="sm"
                                onClick={() =>
                                    setFormState({
                                        mode: 'create',
                                        prefillDebtorId: selectedDebtor?.id ?? null,
                                    })
                                }
                            >
                                <Plus size={16} strokeWidth={2} aria-hidden />
                                Novo empréstimo
                            </Button>
                        ) : null}
                    </div>
                )}
            />

            {!selectedDebtor ? (
                <div
                    className="flex flex-wrap items-center gap-2"
                    role="tablist"
                    aria-label="Seções de devedores"
                >
                    {VIEW_TABS.map((tab) => (
                        <Pill
                            key={tab.id}
                            active={view === tab.id}
                            onClick={() => {
                                setView(tab.id);
                                setSelectedDebtor(null);
                                setPage(1);
                            }}
                        >
                            {tab.label}
                        </Pill>
                    ))}
                </div>
            ) : null}

            {selectedDebtor ? (
                <>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div className="min-w-0">
                            <button
                                type="button"
                                className="mb-2 inline-flex items-center gap-1.5 text-caption font-medium text-ink-secondary transition hover:text-ink"
                                onClick={() => setSelectedDebtor(null)}
                            >
                                <ArrowLeft size={14} strokeWidth={2} aria-hidden />
                                Voltar às pessoas
                            </button>
                            <h2 className="truncate text-h2 font-semibold text-ink">
                                {selectedDebtor.name}
                            </h2>
                            {selectedDebtor.notes ? (
                                <p className="mt-1 text-caption text-ink-secondary">
                                    {selectedDebtor.notes}
                                </p>
                            ) : null}
                            <p className="mt-2 text-caption text-ink-muted">
                                {Number(selectedDebtor.open_loans_count ?? 0)} em aberto
                                {selectedDebtor.open_remaining_total != null
                                    ? ` · ${formatMoney(selectedDebtor.open_remaining_total)}`
                                    : ''}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="secondary"
                                onClick={() => setLinkDebtorTarget(selectedDebtor)}
                            >
                                <Link2 size={16} strokeWidth={2} aria-hidden />
                                Vincular saídas
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="secondary"
                                onClick={() =>
                                    setDebtorForm({
                                        mode: 'edit',
                                        debtor: selectedDebtor,
                                    })
                                }
                            >
                                <Pencil size={16} strokeWidth={2} aria-hidden />
                                Editar
                            </Button>
                        </div>
                    </div>
                    {renderLoanList({ showDebtorColumn: false })}
                </>
            ) : null}

            {!selectedDebtor && view === 'people' ? (
                <>
                    <section className="flex flex-col gap-2" aria-label="Busca de pessoas">
                        <Input
                            type="search"
                            value={peopleQInput}
                            placeholder="Buscar por nome"
                            aria-label="Buscar pessoas"
                            className="min-w-0 w-full sm:max-w-xs"
                            onChange={(event) => setPeopleQInput(event.target.value)}
                        />
                        {peopleRows.length > 0 ? (
                            <p className="text-caption text-ink-muted">
                                {peopleRows.length} pessoa
                                {peopleRows.length === 1 ? '' : 's'}
                            </p>
                        ) : null}
                    </section>

                    {debtors.status === 'error' ? (
                        <ErrorState
                            title="Não foi possível carregar as pessoas"
                            message={debtors.error || 'Tente novamente em instantes.'}
                            onRetry={debtors.refetch}
                        />
                    ) : null}

                    {debtors.status === 'loading' && peopleRows.length === 0 ? (
                        <div
                            className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
                            aria-busy="true"
                            aria-label="Carregando pessoas"
                        >
                            {[0, 1, 2, 3, 4, 5].map((key) => (
                                <Skeleton key={key} className="h-36 w-full" radius="xl" />
                            ))}
                        </div>
                    ) : null}

                    {debtors.status !== 'error' &&
                    debtors.status !== 'loading' &&
                    peopleRows.length === 0 ? (
                        <EmptyState
                            title="Nenhuma pessoa cadastrada"
                            description="Cadastre quem deve a você para vincular empréstimos e saídas."
                            action={{
                                label: 'Cadastrar primeira pessoa',
                                onClick: () => setDebtorForm({ mode: 'create' }),
                            }}
                        />
                    ) : null}

                    {peopleRows.length > 0 ? (
                        <div
                            className={cx(
                                'grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3',
                                debtors.status === 'loading' && 'opacity-60',
                            )}
                        >
                            {peopleRows.map((row) => (
                                <DebtorCard
                                    key={row.id}
                                    row={row}
                                    onOpen={() => setSelectedDebtor(row)}
                                    onLink={() => setLinkDebtorTarget(row)}
                                    onEdit={() =>
                                        setDebtorForm({ mode: 'edit', debtor: row })
                                    }
                                    onDelete={() => setDeleteDebtorTarget(row)}
                                />
                            ))}
                        </div>
                    ) : null}
                </>
            ) : null}

            {!selectedDebtor && view === 'loans' ? (
                <>
                    <section className="flex flex-col gap-2" aria-label="Filtros de empréstimos">
                        <div className="flex items-center gap-2 sm:hidden">
                            <Input
                                type="search"
                                value={qInput}
                                placeholder="Buscar por devedor"
                                aria-label="Buscar empréstimos"
                                className="min-w-0 flex-1"
                                onChange={(event) => setQInput(event.target.value)}
                            />
                            <button
                                type="button"
                                className={cx(
                                    'relative inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border transition',
                                    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                                    activeFilterCount > 0
                                        ? 'border-brand bg-brand/15 text-ink'
                                        : 'border-border bg-transparent text-ink hover:bg-surface-raised',
                                )}
                                aria-label="Abrir filtros"
                                aria-haspopup="dialog"
                                aria-expanded={filtersOpen}
                                onClick={() => setFiltersOpen(true)}
                            >
                                <ListFilter size={18} strokeWidth={1.75} aria-hidden />
                                {activeFilterCount > 0 ? (
                                    <span className="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-brand px-1 text-[10px] font-semibold text-ink-on-brand">
                                        {activeFilterCount}
                                    </span>
                                ) : null}
                            </button>
                        </div>
                        <p className="text-caption text-ink-muted sm:hidden">
                            {activeFilterLabel}
                            {meta?.loans_used != null
                                ? ` · ${meta.loans_used} empréstimo${meta.loans_used === 1 ? '' : 's'} · cota ${remainingLabel}`
                                : null}
                        </p>

                        <div className="hidden flex-col gap-2 sm:flex">
                            <div className="flex flex-wrap items-center gap-2">
                                {statusPills}
                                <Input
                                    type="search"
                                    value={qInput}
                                    placeholder="Buscar por devedor"
                                    aria-label="Buscar empréstimos"
                                    className="min-w-[12rem] flex-1 sm:max-w-xs"
                                    onChange={(event) => setQInput(event.target.value)}
                                />
                            </div>
                            {kindPills}
                        </div>
                        {meta?.loans_used != null ? (
                            <p className="hidden text-caption text-ink-muted sm:block">
                                {meta.loans_used} empréstimo
                                {meta.loans_used === 1 ? '' : 's'} · cota {remainingLabel}
                            </p>
                        ) : null}
                    </section>
                    {renderLoanList({ showDebtorColumn: true })}
                </>
            ) : null}

            <Modal
                open={filtersOpen}
                title="Filtros"
                description="Filtre empréstimos por status e tipo."
                onClose={() => setFiltersOpen(false)}
                size="sm"
                footer={(
                    <Button
                        type="button"
                        variant="primary"
                        size="sm"
                        className="w-full sm:w-auto"
                        onClick={() => setFiltersOpen(false)}
                    >
                        Aplicar
                    </Button>
                )}
            >
                <div className="flex flex-col gap-4">
                    <div className="flex flex-col gap-2">
                        <p className="text-caption font-medium text-ink-secondary">Status</p>
                        {statusPills}
                    </div>
                    <div className="flex flex-col gap-2">
                        <p className="text-caption font-medium text-ink-secondary">Tipo</p>
                        {kindPills}
                    </div>
                </div>
            </Modal>

            <LoanFormModal
                open={formOpen}
                mode={formMode}
                loan={formState?.loan ?? null}
                creditCards={creditCards.data}
                creditCardsLoading={creditCards.status === 'loading'}
                debtors={debtors.data}
                debtorsLoading={debtors.status === 'loading'}
                onCreateDebtor={() => setDebtorForm({ mode: 'create' })}
                categories={categories.data}
                categoriesLoading={categories.status === 'loading'}
                onCategoryCreated={() => {
                    void categories.refresh({ force: true });
                }}
                submitting={formSubmitting}
                onClose={() => {
                    if (!formSubmitting) {
                        setFormState(null);
                    }
                }}
                onSubmit={async (payload) => {
                    if (formMode === 'edit' && formState?.loan?.id != null) {
                        await updateLoan.mutate(formState.loan.id, payload);
                    } else {
                        const withDebtor =
                            formState?.prefillDebtorId && !payload.debtor_id
                                ? { ...payload, debtor_id: formState.prefillDebtorId }
                                : payload;
                        await createLoan.mutate(withDebtor);
                    }
                    setFormState(null);
                }}
            />

            <DebtorFormModal
                open={debtorForm !== null}
                mode={debtorForm?.mode ?? 'create'}
                debtor={debtorForm?.debtor ?? null}
                submitting={createDebtor.isLoading || updateDebtor.isLoading}
                onClose={() => {
                    if (!createDebtor.isLoading && !updateDebtor.isLoading) {
                        setDebtorForm(null);
                    }
                }}
                onSubmit={async (payload) => {
                    if (debtorForm?.mode === 'edit' && debtorForm?.debtor?.id != null) {
                        await updateDebtor.mutate(debtorForm.debtor.id, payload);
                    } else {
                        await createDebtor.mutate(payload);
                    }
                    setDebtorForm(null);
                }}
            />

            <MarkLoanPaidDialog
                open={markPaidTarget !== null}
                loan={markPaidTarget}
                submitting={markPaid.isLoading}
                onClose={() => {
                    if (!markPaid.isLoading) {
                        setMarkPaidTarget(null);
                    }
                }}
                onConfirm={async (payload) => {
                    if (!markPaidTarget?.id) {
                        return;
                    }
                    await markPaid.mutate(markPaidTarget.id, payload);
                    setMarkPaidTarget(null);
                }}
            />

            <CancelLoanDialog
                open={cancelTarget !== null}
                loan={cancelTarget}
                submitting={cancelLoan.isLoading}
                onClose={() => {
                    if (!cancelLoan.isLoading) {
                        setCancelTarget(null);
                    }
                }}
                onConfirm={async () => {
                    if (!cancelTarget?.id) {
                        return;
                    }
                    await cancelLoan.mutate(cancelTarget.id);
                    setCancelTarget(null);
                }}
            />

            <DeleteLoanDialog
                open={deleteTarget !== null}
                loan={deleteTarget}
                submitting={deleteLoan.isLoading}
                onClose={() => {
                    if (!deleteLoan.isLoading) {
                        setDeleteTarget(null);
                    }
                }}
                onConfirm={async () => {
                    if (!deleteTarget?.id) {
                        return;
                    }
                    await deleteLoan.mutate(deleteTarget.id);
                    setDeleteTarget(null);
                }}
            />

            <DeleteDebtorDialog
                open={deleteDebtorTarget !== null}
                debtor={deleteDebtorTarget}
                submitting={deleteDebtorMut.isLoading}
                onClose={() => {
                    if (!deleteDebtorMut.isLoading) {
                        setDeleteDebtorTarget(null);
                    }
                }}
                onConfirm={async () => {
                    if (!deleteDebtorTarget?.id) {
                        return;
                    }
                    await deleteDebtorMut.mutate(deleteDebtorTarget.id);
                    setDeleteDebtorTarget(null);
                }}
            />

            <LinkDebtorTransactionsModal
                open={linkDebtorTarget !== null}
                debtor={linkDebtorTarget}
                onClose={() => setLinkDebtorTarget(null)}
                onLinked={refreshAll}
            />
        </div>
    );
}

function canActOnLoan(loan) {
    return (
        loan?.status === LOAN_STATUSES.open || loan?.status === LOAN_STATUSES.partial
    );
}

function DebtorCard({ row, onOpen, onLink, onEdit, onDelete }) {
    const openCount = Number(row.open_loans_count ?? 0);
    const remaining = Number(row.open_remaining_total ?? 0);

    return (
        <article
            className={cx(
                'group flex flex-col gap-3 rounded-2xl border border-border-subtle bg-surface p-4',
                'transition hover:border-border hover:bg-surface-raised',
            )}
        >
            <button
                type="button"
                className="flex w-full min-w-0 items-start gap-3 text-left"
                onClick={onOpen}
                aria-label={`Abrir dívidas de ${row.name}`}
            >
                <div className="min-w-0 flex-1">
                    <p className="truncate text-body font-semibold text-ink" title={row.name}>
                        {row.name}
                    </p>
                    {row.notes ? (
                        <p
                            className="mt-0.5 line-clamp-2 text-small text-ink-secondary"
                            title={row.notes}
                        >
                            {row.notes}
                        </p>
                    ) : (
                        <p className="mt-0.5 text-small text-ink-muted">
                            Toque para ver as dívidas
                        </p>
                    )}
                    <div className="mt-3 flex flex-wrap items-end justify-between gap-2">
                        <div className="flex flex-col gap-1">
                            {openCount > 0 ? (
                                <Badge tone="danger">{openCount} em aberto</Badge>
                            ) : (
                                <Badge tone="meta">Em dia</Badge>
                            )}
                        </div>
                        <p
                            className={cx(
                                'text-h3 font-semibold tabular-nums',
                                openCount > 0 ? 'text-ink' : 'text-ink-muted',
                            )}
                        >
                            {openCount > 0 ? formatMoney(remaining) : '—'}
                        </p>
                    </div>
                </div>
                <ChevronRight
                    size={18}
                    strokeWidth={1.75}
                    className="mt-1 shrink-0 text-ink-muted transition group-hover:text-ink"
                    aria-hidden
                />
            </button>
            <div className="flex justify-end gap-1 border-t border-border-subtle pt-2">
                <IconAction
                    label={`Vincular saídas a ${row.name}`}
                    onClick={onLink}
                >
                    <Link2 size={16} strokeWidth={1.75} aria-hidden />
                </IconAction>
                <IconAction label={`Editar ${row.name}`} onClick={onEdit}>
                    <Pencil size={16} strokeWidth={1.75} aria-hidden />
                </IconAction>
                <IconAction
                    label={`Excluir ${row.name}`}
                    tone="danger"
                    onClick={onDelete}
                >
                    <Trash2 size={16} strokeWidth={1.75} aria-hidden />
                </IconAction>
            </div>
        </article>
    );
}

function StatusBadges({ loan }) {
    const overdue = isLoanOverdue(loan);

    return (
        <div className="flex flex-wrap items-center gap-1.5">
            {overdue ? (
                <Badge tone="danger">Atrasada</Badge>
            ) : (
                <Badge
                    tone={
                        loan.status === LOAN_STATUSES.paid
                            ? 'positive'
                            : loan.status === LOAN_STATUSES.cancelled
                                ? 'meta'
                                : 'meta'
                    }
                >
                    {loanStatusLabel(loan.status)}
                </Badge>
            )}
            {overdue && loan.status === LOAN_STATUSES.partial ? (
                <Badge tone="meta">{loanStatusLabel(loan.status)}</Badge>
            ) : null}
        </div>
    );
}

function LoanMobileRow({ row, onEdit, onMarkPaid, onCancel, onDelete, onOpenPlan }) {
    const actionable = canActOnLoan(row);

    return (
        <li className="border-b border-border-subtle/60 px-3 py-3 last:border-b-0">
            <div className="min-w-0">
                <p className="truncate text-body font-semibold text-ink" title={row.debtor_name}>
                    {row.debtor_name}
                </p>
                <p className="mt-0.5 text-small text-ink-secondary">
                    {loanKindLabel(row.kind)} · {formatMoney(row.amount)}
                    {row.credit_card?.name ? ` · ${row.credit_card.name}` : ''}
                </p>
                {row.notes ? (
                    <p className="mt-0.5 truncate text-small text-ink-muted" title={row.notes}>
                        {row.notes}
                    </p>
                ) : null}
                <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                    <StatusBadges loan={row} />
                    {row.installment_plan_id && onOpenPlan ? (
                        <button type="button" onClick={onOpenPlan}>
                            <Badge tone="meta">Parcelamento</Badge>
                        </button>
                    ) : null}
                    <span className="text-small text-ink-muted">
                        Restante {formatMoney(row.remaining_amount)}
                    </span>
                </div>
                <p className="mt-1 text-small text-ink-muted">
                    Cobrar em {formatDate(row.due_on)}
                </p>
            </div>
            <div className="mt-2 flex justify-end gap-1">
                <IconAction label={`Editar ${row.debtor_name}`} onClick={onEdit}>
                    <Pencil size={16} strokeWidth={1.75} aria-hidden />
                </IconAction>
                {actionable ? (
                    <>
                        <IconAction
                            label={`Registrar pagamento de ${row.debtor_name}`}
                            onClick={onMarkPaid}
                        >
                            <Banknote size={16} strokeWidth={1.75} aria-hidden />
                        </IconAction>
                        <IconAction
                            label={`Cancelar empréstimo de ${row.debtor_name}`}
                            onClick={onCancel}
                        >
                            <Ban size={16} strokeWidth={1.75} aria-hidden />
                        </IconAction>
                    </>
                ) : null}
                <IconAction
                    label={`Excluir ${row.debtor_name}`}
                    tone="danger"
                    onClick={onDelete}
                >
                    <Trash2 size={16} strokeWidth={1.75} aria-hidden />
                </IconAction>
            </div>
        </li>
    );
}

function LoanDesktopRow({
    row,
    showDebtor = true,
    onEdit,
    onMarkPaid,
    onCancel,
    onDelete,
    onOpenPlan,
}) {
    const actionable = canActOnLoan(row);
    const overdue = isLoanOverdue(row);
    const title = showDebtor
        ? row.debtor_name
        : (row.notes?.replace(/^Criado ao vincular lançamento:\s*/i, '') || row.debtor_name);

    return (
        <tr className="border-b border-border-subtle/60 transition hover:bg-surface-raised">
            <td className="max-w-[14rem] px-4 py-3">
                <span className="block truncate font-medium" title={title}>
                    {title}
                </span>
                {row.installment_plan_id && onOpenPlan ? (
                    <button
                        type="button"
                        className="mt-1"
                        onClick={onOpenPlan}
                    >
                        <Badge tone="meta">Parcelamento</Badge>
                    </button>
                ) : null}
            </td>
            <td className="px-4 py-3">
                <Badge tone="meta">{loanKindLabel(row.kind)}</Badge>
            </td>
            <td className="px-4 py-3 tabular-nums text-ink-secondary">
                {formatMoney(row.amount)}
            </td>
            <td className="px-4 py-3 tabular-nums text-ink-secondary">
                {formatMoney(row.remaining_amount)}
            </td>
            <td
                className={cx(
                    'px-4 py-3 tabular-nums',
                    overdue ? 'font-medium text-feedback-danger' : 'text-ink-secondary',
                )}
            >
                {formatDate(row.due_on)}
            </td>
            <td className="px-4 py-3">
                <StatusBadges loan={row} />
            </td>
            <td className="max-w-[8rem] px-4 py-3 text-ink-secondary">
                <span className="block truncate" title={row.credit_card?.name ?? undefined}>
                    {row.credit_card?.name ?? '—'}
                </span>
            </td>
            <td className="whitespace-nowrap px-3 py-2 text-right">
                <div className="flex items-center justify-end gap-1">
                    <IconAction label={`Editar ${row.debtor_name}`} onClick={onEdit}>
                        <Pencil size={16} strokeWidth={1.75} aria-hidden />
                    </IconAction>
                    {actionable ? (
                        <>
                            <IconAction
                                label={`Registrar pagamento de ${row.debtor_name}`}
                                onClick={onMarkPaid}
                            >
                                <Banknote size={16} strokeWidth={1.75} aria-hidden />
                            </IconAction>
                            <IconAction
                                label={`Cancelar empréstimo de ${row.debtor_name}`}
                                onClick={onCancel}
                            >
                                <Ban size={16} strokeWidth={1.75} aria-hidden />
                            </IconAction>
                        </>
                    ) : null}
                    <IconAction
                        label={`Excluir ${row.debtor_name}`}
                        tone="danger"
                        onClick={onDelete}
                    >
                        <Trash2 size={16} strokeWidth={1.75} aria-hidden />
                    </IconAction>
                </div>
            </td>
        </tr>
    );
}

function IconAction({ label, onClick, children, tone = 'default' }) {
    return (
        <button
            type="button"
            className={cx(
                'inline-flex h-9 w-9 items-center justify-center rounded-full transition',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                tone === 'danger'
                    ? 'text-ink-secondary hover:bg-surface-raised hover:text-feedback-danger'
                    : 'text-ink-secondary hover:bg-surface-raised hover:text-ink',
            )}
            aria-label={label}
            title={label}
            onClick={onClick}
        >
            {children}
        </button>
    );
}

import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link2, ListFilter, Pencil, Plus, Trash2 } from 'lucide-react';
import CreditCardFormModal from '../components/creditCards/CreditCardFormModal';
import DeleteCreditCardDialog from '../components/creditCards/DeleteCreditCardDialog';
import LinkCardTransactionsModal from '../components/creditCards/LinkCardTransactionsModal';
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
import {
    useCreateCreditCard,
    useDeleteCreditCard,
    useUpdateCreditCard,
} from '../hooks/useCreditCardMutations';
import { useDocumentTitle } from '../hooks/useDocumentTitle';
import { creditCardStatusLabel } from '../lib/creditCards';
import { cx } from '../lib/cx';
import { formatDate, formatMoney } from '../lib/format';

const ACTIVE_FILTERS = [
    { id: '', label: 'Todos' },
    { id: '1', label: 'Ativos' },
    { id: '0', label: 'Inativos' },
];

/**
 * CardsPage — CRUD de cartões (PLAN_CARTOES_EMPRESTIMOS §6.3).
 */
export default function CardsPage() {
    useDocumentTitle('Cartões · Aura');

    const [qInput, setQInput] = useState('');
    const [q, setQ] = useState('');
    const [activeFilter, setActiveFilter] = useState('');
    const [page, setPage] = useState(1);
    const [filtersOpen, setFiltersOpen] = useState(false);

    const [formState, setFormState] = useState(null);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [linkTarget, setLinkTarget] = useState(null);

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setQ(qInput.trim());
            setPage(1);
        }, 300);

        return () => window.clearTimeout(timer);
    }, [qInput]);

    const filters = useMemo(() => {
        /** @type {Record<string, string|number>} */
        const params = { page, per_page: 50 };

        if (q) {
            params.q = q;
        }

        if (activeFilter !== '') {
            params.is_active = activeFilter;
        }

        return params;
    }, [q, activeFilter, page]);

    const cards = useCreditCards(filters);

    const refresh = useCallback(async () => {
        await cards.refetch();
    }, [cards.refetch]);

    const createCard = useCreateCreditCard({ onSuccess: refresh });
    const updateCard = useUpdateCreditCard({ onSuccess: refresh });
    const deleteCard = useDeleteCreditCard({ onSuccess: refresh });

    const formOpen = formState !== null;
    const formMode = formState?.mode === 'edit' ? 'edit' : 'create';
    const formSubmitting =
        formMode === 'edit' ? updateCard.isLoading : createCard.isLoading;

    const rows = cards.data;
    const meta = cards.meta;

    const remainingLabel =
        meta?.credit_cards_remaining == null
            ? 'ilimitado'
            : `${meta.credit_cards_remaining} restante${meta.credit_cards_remaining === 1 ? '' : 's'}`;

    const activeFilterCount = activeFilter !== '' ? 1 : 0;
    const activeFilterLabel =
        ACTIVE_FILTERS.find((option) => option.id === activeFilter)?.label ?? 'Todos';

    const statusPills = (
        <div className="flex flex-wrap items-center gap-2" role="group" aria-label="Status">
            {ACTIVE_FILTERS.map((option) => (
                <Pill
                    key={option.id || 'all'}
                    active={activeFilter === option.id}
                    onClick={() => {
                        setActiveFilter(option.id);
                        setPage(1);
                    }}
                >
                    {option.label}
                </Pill>
            ))}
        </div>
    );

    return (
        <div className="flex flex-col gap-8 md:gap-10">
            <PageHeader
                title="Cartões"
                description="Cadastre cartões e acompanhe fechamento e vencimento."
                actions={(
                    <Button
                        type="button"
                        size="sm"
                        onClick={() => setFormState({ mode: 'create' })}
                    >
                        <Plus size={16} strokeWidth={2} aria-hidden />
                        Novo cartão
                    </Button>
                )}
            />

            <section className="flex flex-col gap-2" aria-label="Filtros de cartões">
                <div className="flex items-center gap-2 sm:hidden">
                    <Input
                        type="search"
                        value={qInput}
                        placeholder="Buscar por nome"
                        aria-label="Buscar cartões"
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
                    {meta?.credit_cards_used != null
                        ? ` · ${meta.credit_cards_used} cartão${meta.credit_cards_used === 1 ? '' : 's'} · cota ${remainingLabel}`
                        : null}
                </p>

                <div className="hidden flex-wrap items-center gap-2 sm:flex">
                    {statusPills}
                    <Input
                        type="search"
                        value={qInput}
                        placeholder="Buscar por nome"
                        aria-label="Buscar cartões"
                        className="min-w-[12rem] flex-1 sm:max-w-xs"
                        onChange={(event) => setQInput(event.target.value)}
                    />
                </div>
                {meta?.credit_cards_used != null ? (
                    <p className="hidden text-caption text-ink-muted sm:block">
                        {meta.credit_cards_used} cartão{meta.credit_cards_used === 1 ? '' : 's'} · cota{' '}
                        {remainingLabel}
                    </p>
                ) : null}
            </section>

            {cards.status === 'error' ? (
                <ErrorState
                    title="Não foi possível carregar os cartões"
                    message={cards.error || 'Tente novamente em instantes.'}
                    onRetry={cards.refetch}
                />
            ) : null}

            {cards.status === 'loading' && rows.length === 0 ? (
                <div aria-busy="true" aria-label="Carregando cartões">
                    <Skeleton.Table rows={5} />
                </div>
            ) : null}

            {cards.status !== 'error' &&
            cards.status !== 'loading' &&
            rows.length === 0 ? (
                <EmptyState
                    title="Nenhum cartão ainda"
                    description="Cadastre um cartão para acompanhar fechamento e vencimento."
                    action={{
                        label: 'Cadastrar primeiro cartão',
                        onClick: () => setFormState({ mode: 'create' }),
                    }}
                />
            ) : null}

            {rows.length > 0 ? (
                <div className="overflow-hidden rounded-xl border border-border-subtle bg-surface">
                    <ul
                        className={cx(
                            'md:hidden',
                            cards.status === 'loading' && 'opacity-60',
                        )}
                    >
                        {rows.map((row) => (
                            <li
                                key={row.id}
                                className="border-b border-border-subtle/60 px-3 py-3 last:border-b-0"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <p
                                            className="truncate text-body font-semibold text-ink"
                                            title={row.name}
                                        >
                                            {row.name}
                                            {row.last_four ? (
                                                <span className="ml-1.5 font-normal text-ink-muted">
                                                    ···· {row.last_four}
                                                </span>
                                            ) : null}
                                        </p>
                                        <p className="mt-0.5 text-small text-ink-secondary">
                                            Limite{' '}
                                            {row.limit_amount != null
                                                ? formatMoney(row.limit_amount)
                                                : '—'}
                                        </p>
                                        <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                                            <Badge tone="meta">
                                                Fecha dia {row.closing_day}
                                            </Badge>
                                            <Badge tone="meta">
                                                Vence dia {row.due_day}
                                            </Badge>
                                            <Badge tone={row.is_active ? 'positive' : 'danger'}>
                                                {creditCardStatusLabel(row)}
                                            </Badge>
                                            {row.is_default ? (
                                                <Badge tone="brand">Padrão</Badge>
                                            ) : null}
                                        </div>
                                        <p className="mt-1 text-small text-ink-muted">
                                            Próx. vencimento {formatDate(row.next_due_on)}
                                        </p>
                                    </div>
                                </div>
                                <div className="mt-2 flex justify-end gap-1">
                                    <IconAction
                                        label={`Vincular saídas a ${row.name}`}
                                        onClick={() => setLinkTarget(row)}
                                    >
                                        <Link2 size={16} strokeWidth={1.75} aria-hidden />
                                    </IconAction>
                                    <IconAction
                                        label={`Editar ${row.name}`}
                                        onClick={() =>
                                            setFormState({ mode: 'edit', card: row })
                                        }
                                    >
                                        <Pencil size={16} strokeWidth={1.75} aria-hidden />
                                    </IconAction>
                                    <IconAction
                                        label={`Excluir ${row.name}`}
                                        tone="danger"
                                        onClick={() => setDeleteTarget(row)}
                                    >
                                        <Trash2 size={16} strokeWidth={1.75} aria-hidden />
                                    </IconAction>
                                </div>
                            </li>
                        ))}
                    </ul>

                    <div className="hidden overflow-x-auto md:block">
                        <table className="min-w-[56rem] w-full border-collapse text-body text-ink">
                            <thead>
                                <tr className="border-b border-border-subtle text-left text-caption font-medium text-ink-secondary">
                                    <th className="px-4 py-3">Nome</th>
                                    <th className="px-4 py-3">Limite</th>
                                    <th className="px-4 py-3">Fechamento</th>
                                    <th className="px-4 py-3">Vencimento</th>
                                    <th className="px-4 py-3">Próx. vencimento</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3 text-right">
                                        <span className="sr-only">Ações</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                className={
                                    cards.status === 'loading' ? 'opacity-60' : undefined
                                }
                            >
                                {rows.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-b border-border-subtle/60 transition hover:bg-surface-raised"
                                    >
                                        <td className="max-w-[14rem] px-4 py-3">
                                            <span
                                                className="block truncate font-medium"
                                                title={row.name}
                                            >
                                                {row.name}
                                            </span>
                                            {row.last_four ? (
                                                <span className="text-small text-ink-muted">
                                                    ···· {row.last_four}
                                                </span>
                                            ) : null}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums text-ink-secondary">
                                            {row.limit_amount != null
                                                ? formatMoney(row.limit_amount)
                                                : '—'}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums text-ink-secondary">
                                            Dia {row.closing_day}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums text-ink-secondary">
                                            Dia {row.due_day}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums text-ink-secondary">
                                            {formatDate(row.next_due_on)}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap items-center gap-1.5">
                                                <Badge tone={row.is_active ? 'positive' : 'danger'}>
                                                    {creditCardStatusLabel(row)}
                                                </Badge>
                                                {row.is_default ? (
                                                    <Badge tone="brand">Padrão</Badge>
                                                ) : null}
                                            </div>
                                        </td>
                                        <td className="whitespace-nowrap px-3 py-2 text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                <IconAction
                                                    label={`Vincular saídas a ${row.name}`}
                                                    onClick={() => setLinkTarget(row)}
                                                >
                                                    <Link2
                                                        size={16}
                                                        strokeWidth={1.75}
                                                        aria-hidden
                                                    />
                                                </IconAction>
                                                <IconAction
                                                    label={`Editar ${row.name}`}
                                                    onClick={() =>
                                                        setFormState({
                                                            mode: 'edit',
                                                            card: row,
                                                        })
                                                    }
                                                >
                                                    <Pencil
                                                        size={16}
                                                        strokeWidth={1.75}
                                                        aria-hidden
                                                    />
                                                </IconAction>
                                                <IconAction
                                                    label={`Excluir ${row.name}`}
                                                    tone="danger"
                                                    onClick={() => setDeleteTarget(row)}
                                                >
                                                    <Trash2
                                                        size={16}
                                                        strokeWidth={1.75}
                                                        aria-hidden
                                                    />
                                                </IconAction>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            ) : null}

            {meta && meta.last_page > 1 ? (
                <div className="flex items-center justify-center gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        disabled={page <= 1 || cards.status === 'loading'}
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
                            page >= meta.last_page || cards.status === 'loading'
                        }
                        onClick={() => setPage((p) => p + 1)}
                    >
                        Próxima
                    </Button>
                </div>
            ) : null}

            <Modal
                open={filtersOpen}
                title="Filtros"
                description="Filtre cartões por status."
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
                <div className="flex flex-col gap-2">
                    <p className="text-caption font-medium text-ink-secondary">Status</p>
                    {statusPills}
                </div>
            </Modal>

            <CreditCardFormModal
                open={formOpen}
                mode={formMode}
                card={formState?.card ?? null}
                submitting={formSubmitting}
                onClose={() => {
                    if (!formSubmitting) {
                        setFormState(null);
                    }
                }}
                onSubmit={async (payload) => {
                    if (formMode === 'edit' && formState?.card?.id != null) {
                        await updateCard.mutate(formState.card.id, payload);
                    } else {
                        await createCard.mutate(payload);
                    }
                    setFormState(null);
                }}
            />

            <DeleteCreditCardDialog
                open={deleteTarget !== null}
                card={deleteTarget}
                submitting={deleteCard.isLoading}
                onClose={() => {
                    if (!deleteCard.isLoading) {
                        setDeleteTarget(null);
                    }
                }}
                onConfirm={async () => {
                    if (!deleteTarget?.id) {
                        return;
                    }
                    await deleteCard.mutate(deleteTarget.id);
                    setDeleteTarget(null);
                }}
            />

            <LinkCardTransactionsModal
                open={linkTarget !== null}
                card={linkTarget}
                onClose={() => setLinkTarget(null)}
            />
        </div>
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

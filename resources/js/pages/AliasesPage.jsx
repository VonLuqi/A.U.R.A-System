import { useCallback, useEffect, useMemo, useState } from 'react';
import { ListFilter, Pencil, Plus, Trash2 } from 'lucide-react';
import AliasFormModal from '../components/aliases/AliasFormModal';
import DeleteAliasDialog from '../components/aliases/DeleteAliasDialog';
import PageHeader from '../components/layout/PageHeader';
import Badge from '../components/ui/Badge';
import Button from '../components/ui/Button';
import EmptyState from '../components/ui/EmptyState';
import ErrorState from '../components/ui/ErrorState';
import Input from '../components/ui/Input';
import Modal from '../components/ui/Modal';
import Pill from '../components/ui/Pill';
import Skeleton from '../components/ui/Skeleton';
import { useAliases } from '../hooks/useAliases';
import {
    useCreateAlias,
    useDeleteAlias,
    useUpdateAlias,
} from '../hooks/useAliasMutations';
import { useCategories } from '../hooks/useCategories';
import { useDocumentTitle } from '../hooks/useDocumentTitle';
import { cx } from '../lib/cx';
import { ALIAS_MATCH_TYPE_LABELS } from '../lib/aliases';

const ACTIVE_FILTERS = [
    { id: '', label: 'Todas' },
    { id: '1', label: 'Ativas' },
    { id: '0', label: 'Inativas' },
];

/**
 * AliasesPage — CRUD de apelidos/regras (PLAN_EXPANSAO §8.7).
 */
export default function AliasesPage() {
    useDocumentTitle('Apelidos · Aura');

    const categories = useCategories();

    const [qInput, setQInput] = useState('');
    const [q, setQ] = useState('');
    const [activeFilter, setActiveFilter] = useState('');
    const [page, setPage] = useState(1);
    const [filtersOpen, setFiltersOpen] = useState(false);

    const [formState, setFormState] = useState(null);
    const [deleteTarget, setDeleteTarget] = useState(null);

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

    const aliases = useAliases(filters);

    const refresh = useCallback(async () => {
        await aliases.refetch();
    }, [aliases.refetch]);

    const createAlias = useCreateAlias({ onSuccess: refresh });
    const updateAlias = useUpdateAlias({ onSuccess: refresh });
    const deleteAlias = useDeleteAlias({ onSuccess: refresh });

    const formOpen = formState !== null;
    const formMode = formState?.mode === 'edit' ? 'edit' : 'create';
    const formSubmitting =
        formMode === 'edit' ? updateAlias.isLoading : createAlias.isLoading;

    const rows = aliases.data;
    const meta = aliases.meta;

    const remainingLabel =
        meta?.aliases_remaining == null
            ? 'ilimitado'
            : `${meta.aliases_remaining} restante${meta.aliases_remaining === 1 ? '' : 's'}`;

    const activeFilterCount = activeFilter !== '' ? 1 : 0;
    const activeFilterLabel =
        ACTIVE_FILTERS.find((option) => option.id === activeFilter)?.label ?? 'Todas';

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
                title="Apelidos"
                description="Regras para renomear e categorizar lançamentos automaticamente."
                actions={(
                    <Button
                        type="button"
                        size="sm"
                        onClick={() => setFormState({ mode: 'create' })}
                    >
                        <Plus size={16} strokeWidth={2} aria-hidden />
                        Novo apelido
                    </Button>
                )}
            />

            <section className="flex flex-col gap-2" aria-label="Filtros de apelidos">
                <div className="flex items-center gap-2 sm:hidden">
                    <Input
                        type="search"
                        value={qInput}
                        placeholder="Buscar padrão ou apelido"
                        aria-label="Buscar apelidos"
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
                    {meta?.aliases_used != null
                        ? ` · ${meta.aliases_used} regra${meta.aliases_used === 1 ? '' : 's'} · cota ${remainingLabel}`
                        : null}
                </p>

                <div className="hidden flex-wrap items-center gap-2 sm:flex">
                    {statusPills}
                    <Input
                        type="search"
                        value={qInput}
                        placeholder="Buscar padrão ou apelido"
                        aria-label="Buscar apelidos"
                        className="min-w-[12rem] flex-1 sm:max-w-xs"
                        onChange={(event) => setQInput(event.target.value)}
                    />
                </div>
                {meta?.aliases_used != null ? (
                    <p className="hidden text-caption text-ink-muted sm:block">
                        {meta.aliases_used} regra{meta.aliases_used === 1 ? '' : 's'} · cota{' '}
                        {remainingLabel}
                    </p>
                ) : null}
            </section>

            {aliases.status === 'error' ? (
                <ErrorState
                    title="Não foi possível carregar os apelidos"
                    message={aliases.error || 'Tente novamente em instantes.'}
                    onRetry={aliases.refetch}
                />
            ) : null}

            {aliases.status === 'loading' && rows.length === 0 ? (
                <div aria-busy="true" aria-label="Carregando apelidos">
                    <Skeleton.Table rows={5} />
                </div>
            ) : null}

            {aliases.status !== 'error' &&
            aliases.status !== 'loading' &&
            rows.length === 0 ? (
                <EmptyState
                    title="Nenhuma regra ainda"
                    description="Crie um apelido ou use “Lembrar apelido” na tabela do dashboard."
                    action={{
                        label: 'Criar primeiro apelido',
                        onClick: () => setFormState({ mode: 'create' }),
                    }}
                />
            ) : null}

            {rows.length > 0 ? (
                <div className="overflow-hidden rounded-xl border border-border-subtle bg-surface">
                    <ul
                        className={cx(
                            'md:hidden',
                            aliases.status === 'loading' && 'opacity-60',
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
                                            title={row.display_name}
                                        >
                                            {row.display_name}
                                        </p>
                                        <p
                                            className="mt-0.5 truncate text-small text-ink-muted"
                                            title={row.match_pattern}
                                        >
                                            {row.match_pattern}
                                        </p>
                                        <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                                            <Badge tone="meta">
                                                {ALIAS_MATCH_TYPE_LABELS[row.match_type] ??
                                                    row.match_type}
                                            </Badge>
                                            <Badge tone={row.is_active ? 'positive' : 'danger'}>
                                                {row.is_active ? 'Ativo' : 'Inativo'}
                                            </Badge>
                                            {row.category?.name ? (
                                                <span className="text-small text-ink-secondary">
                                                    {row.category.name}
                                                </span>
                                            ) : null}
                                            <span className="text-small tabular-nums text-ink-muted">
                                                pri {row.priority}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div className="mt-2 flex justify-end gap-1">
                                    <IconAction
                                        label={`Editar ${row.display_name}`}
                                        onClick={() =>
                                            setFormState({ mode: 'edit', alias: row })
                                        }
                                    >
                                        <Pencil size={16} strokeWidth={1.75} aria-hidden />
                                    </IconAction>
                                    <IconAction
                                        label={`Excluir ${row.display_name}`}
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
                        <table className="min-w-[48rem] w-full border-collapse text-body text-ink">
                            <thead>
                                <tr className="border-b border-border-subtle text-left text-caption font-medium text-ink-secondary">
                                    <th className="px-4 py-3">Padrão</th>
                                    <th className="px-4 py-3">Tipo</th>
                                    <th className="px-4 py-3">Apelido</th>
                                    <th className="px-4 py-3">Categoria</th>
                                    <th className="px-4 py-3">Prioridade</th>
                                    <th className="px-4 py-3">Ativo</th>
                                    <th className="px-4 py-3 text-right">
                                        <span className="sr-only">Ações</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                className={
                                    aliases.status === 'loading' ? 'opacity-60' : undefined
                                }
                            >
                                {rows.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-b border-border-subtle/60 transition hover:bg-surface-raised"
                                    >
                                        <td className="max-w-[12rem] px-4 py-3">
                                            <span
                                                className="block truncate font-medium"
                                                title={row.match_pattern}
                                            >
                                                {row.match_pattern}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge tone="meta">
                                                {ALIAS_MATCH_TYPE_LABELS[row.match_type] ??
                                                    row.match_type}
                                            </Badge>
                                        </td>
                                        <td className="max-w-[10rem] px-4 py-3">
                                            <span
                                                className="block truncate"
                                                title={row.display_name}
                                            >
                                                {row.display_name}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-ink-secondary">
                                            {row.category?.name ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums text-ink-secondary">
                                            {row.priority}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge tone={row.is_active ? 'positive' : 'danger'}>
                                                {row.is_active ? 'Sim' : 'Não'}
                                            </Badge>
                                        </td>
                                        <td className="whitespace-nowrap px-3 py-2 text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                <IconAction
                                                    label={`Editar ${row.display_name}`}
                                                    onClick={() =>
                                                        setFormState({
                                                            mode: 'edit',
                                                            alias: row,
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
                                                    label={`Excluir ${row.display_name}`}
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
                        disabled={page <= 1 || aliases.status === 'loading'}
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
                            page >= meta.last_page || aliases.status === 'loading'
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
                description="Filtre apelidos por status."
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

            <AliasFormModal
                open={formOpen}
                mode={formMode}
                alias={formState?.alias ?? null}
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
                    if (formMode === 'edit' && formState?.alias?.id != null) {
                        await updateAlias.mutate(formState.alias.id, payload);
                    } else {
                        await createAlias.mutate(payload);
                    }
                    setFormState(null);
                }}
            />

            <DeleteAliasDialog
                open={deleteTarget !== null}
                alias={deleteTarget}
                submitting={deleteAlias.isLoading}
                onClose={() => {
                    if (!deleteAlias.isLoading) {
                        setDeleteTarget(null);
                    }
                }}
                onConfirm={async () => {
                    if (!deleteTarget?.id) {
                        return;
                    }
                    await deleteAlias.mutate(deleteTarget.id);
                    setDeleteTarget(null);
                }}
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

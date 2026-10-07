import { useCallback, useEffect, useMemo, useState } from 'react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import CategoryFormModal from '../components/categories/CategoryFormModal';
import DeleteCategoryDialog from '../components/categories/DeleteCategoryDialog';
import PageHeader from '../components/layout/PageHeader';
import Badge from '../components/ui/Badge';
import Button from '../components/ui/Button';
import EmptyState from '../components/ui/EmptyState';
import ErrorState from '../components/ui/ErrorState';
import Input from '../components/ui/Input';
import Pill from '../components/ui/Pill';
import Skeleton from '../components/ui/Skeleton';
import { useCategories } from '../hooks/useCategories';
import {
    useCreateCategory,
    useDeleteCategory,
    useUpdateCategory,
} from '../hooks/useCategoryMutations';
import { useDocumentTitle } from '../hooks/useDocumentTitle';
import { categoryTypeLabel } from '../lib/categories';
import { cx } from '../lib/cx';

const TYPE_FILTERS = [
    { id: '', label: 'Todas' },
    { id: 'income', label: 'Entradas' },
    { id: 'expense', label: 'Saídas' },
    { id: 'transfer', label: 'Transferências' },
];

/**
 * CategoriesPage — CRUD básico de categorias.
 */
export default function CategoriesPage() {
    useDocumentTitle('Categorias · Aura');

    const categories = useCategories();
    const [qInput, setQInput] = useState('');
    const [q, setQ] = useState('');
    const [typeFilter, setTypeFilter] = useState('');
    const [formState, setFormState] = useState(null);
    const [deleteTarget, setDeleteTarget] = useState(null);

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setQ(qInput.trim().toLowerCase());
        }, 300);

        return () => window.clearTimeout(timer);
    }, [qInput]);

    const refresh = useCallback(async () => {
        await categories.refresh({ force: true });
    }, [categories.refresh]);

    const createCategory = useCreateCategory({ onSuccess: refresh });
    const updateCategory = useUpdateCategory({ onSuccess: refresh });
    const deleteCategory = useDeleteCategory({ onSuccess: refresh });

    const formOpen = formState !== null;
    const formMode = formState?.mode === 'edit' ? 'edit' : 'create';
    const formSubmitting =
        formMode === 'edit' ? updateCategory.isLoading : createCategory.isLoading;

    const rows = useMemo(() => {
        let list = categories.data ?? [];

        if (typeFilter) {
            list = list.filter((row) => row.type === typeFilter);
        }

        if (q) {
            list = list.filter((row) => String(row.name ?? '').toLowerCase().includes(q));
        }

        return list;
    }, [categories.data, typeFilter, q]);

    return (
        <div className="flex flex-col gap-8 md:gap-10">
            <PageHeader
                title="Categorias"
                description="Organize entradas, saídas e transferências do catálogo."
                actions={(
                    <Button
                        type="button"
                        size="sm"
                        onClick={() => setFormState({ mode: 'create' })}
                    >
                        <Plus size={16} strokeWidth={2} aria-hidden />
                        Nova categoria
                    </Button>
                )}
            />

            <section className="flex flex-col gap-2" aria-label="Filtros de categorias">
                <div className="flex flex-wrap items-center gap-2">
                    <div className="flex flex-wrap items-center gap-2" role="group" aria-label="Tipo">
                        {TYPE_FILTERS.map((option) => (
                            <Pill
                                key={option.id || 'all'}
                                active={typeFilter === option.id}
                                onClick={() => setTypeFilter(option.id)}
                            >
                                {option.label}
                            </Pill>
                        ))}
                    </div>
                    <Input
                        type="search"
                        value={qInput}
                        placeholder="Buscar por nome"
                        aria-label="Buscar categorias"
                        className="min-w-[12rem] flex-1 sm:max-w-xs"
                        onChange={(event) => setQInput(event.target.value)}
                    />
                </div>
            </section>

            {categories.status === 'error' ? (
                <ErrorState
                    title="Não foi possível carregar as categorias"
                    message={categories.error || 'Tente novamente em instantes.'}
                    onRetry={() => categories.refresh({ force: true })}
                />
            ) : null}

            {categories.status === 'loading' && rows.length === 0 ? (
                <div aria-busy="true" aria-label="Carregando categorias">
                    <Skeleton.Table rows={5} />
                </div>
            ) : null}

            {categories.status !== 'error'
            && categories.status !== 'loading'
            && rows.length === 0 ? (
                <EmptyState
                    title={q || typeFilter ? 'Nenhuma categoria neste filtro' : 'Nenhuma categoria ainda'}
                    description={
                        q || typeFilter
                            ? 'Ajuste a busca ou o tipo, ou crie uma nova categoria.'
                            : 'Crie categorias para organizar suas movimentações.'
                    }
                    action={{
                        label: 'Nova categoria',
                        onClick: () => setFormState({ mode: 'create' }),
                    }}
                />
            ) : null}

            {rows.length > 0 ? (
                <div className="overflow-hidden rounded-xl border border-border-subtle bg-surface">
                    <ul
                        className={cx(
                            'md:hidden',
                            categories.status === 'loading' && 'opacity-60',
                        )}
                    >
                        {rows.map((row) => (
                            <li
                                key={row.id}
                                className="border-b border-border-subtle/60 px-3 py-3 last:border-b-0"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2">
                                            <ColorDot color={row.color} />
                                            <p className="truncate text-body font-semibold text-ink">
                                                {row.name}
                                            </p>
                                        </div>
                                        <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                                            <Badge tone="meta">{categoryTypeLabel(row.type)}</Badge>
                                            {row.is_system ? (
                                                <Badge tone="meta">Sistema</Badge>
                                            ) : null}
                                            <span className="text-small tabular-nums text-ink-muted">
                                                {Number(row.usage_count ?? 0)} vínculo
                                                {Number(row.usage_count ?? 0) === 1 ? '' : 's'}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div className="mt-2 flex justify-end gap-1">
                                    <IconAction
                                        label={`Editar ${row.name}`}
                                        onClick={() =>
                                            setFormState({ mode: 'edit', category: row })
                                        }
                                    >
                                        <Pencil size={16} strokeWidth={1.75} aria-hidden />
                                    </IconAction>
                                    {!row.is_system ? (
                                        <IconAction
                                            label={`Excluir ${row.name}`}
                                            tone="danger"
                                            onClick={() => setDeleteTarget(row)}
                                        >
                                            <Trash2 size={16} strokeWidth={1.75} aria-hidden />
                                        </IconAction>
                                    ) : null}
                                </div>
                            </li>
                        ))}
                    </ul>

                    <div className="hidden overflow-x-auto md:block">
                        <table className="w-full min-w-[36rem] border-collapse text-left">
                            <thead>
                                <tr className="border-b border-border-subtle text-caption text-ink-muted">
                                    <th className="px-4 py-3 font-medium">Nome</th>
                                    <th className="px-4 py-3 font-medium">Tipo</th>
                                    <th className="px-4 py-3 font-medium">Uso</th>
                                    <th className="px-4 py-3 font-medium"> </th>
                                </tr>
                            </thead>
                            <tbody
                                className={cx(
                                    categories.status === 'loading' && 'opacity-60',
                                )}
                            >
                                {rows.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-b border-border-subtle/60 last:border-b-0"
                                    >
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2">
                                                <ColorDot color={row.color} />
                                                <span className="font-medium text-ink">
                                                    {row.name}
                                                </span>
                                                {row.is_system ? (
                                                    <Badge tone="meta">Sistema</Badge>
                                                ) : null}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-body text-ink-secondary">
                                            {categoryTypeLabel(row.type)}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums text-body text-ink-muted">
                                            {Number(row.usage_count ?? 0)}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-1">
                                                <IconAction
                                                    label={`Editar ${row.name}`}
                                                    onClick={() =>
                                                        setFormState({
                                                            mode: 'edit',
                                                            category: row,
                                                        })
                                                    }
                                                >
                                                    <Pencil
                                                        size={16}
                                                        strokeWidth={1.75}
                                                        aria-hidden
                                                    />
                                                </IconAction>
                                                {!row.is_system ? (
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
                                                ) : null}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            ) : null}

            <CategoryFormModal
                open={formOpen}
                mode={formMode}
                category={formMode === 'edit' ? formState?.category : null}
                submitting={formSubmitting}
                onClose={() => {
                    if (!formSubmitting) {
                        setFormState(null);
                    }
                }}
                onSubmit={async (payload) => {
                    if (formMode === 'edit' && formState?.category?.id) {
                        await updateCategory.mutate(formState.category.id, payload);
                    } else {
                        await createCategory.mutate(payload);
                    }
                    setFormState(null);
                }}
            />

            <DeleteCategoryDialog
                open={deleteTarget !== null}
                category={deleteTarget}
                submitting={deleteCategory.isLoading}
                onClose={() => {
                    if (!deleteCategory.isLoading) {
                        setDeleteTarget(null);
                    }
                }}
                onConfirm={async () => {
                    if (!deleteTarget?.id) {
                        return;
                    }
                    await deleteCategory.mutate(deleteTarget.id);
                    setDeleteTarget(null);
                }}
            />
        </div>
    );
}

function ColorDot({ color }) {
    return (
        <span
            className="inline-block size-3 shrink-0 rounded-full border border-border"
            style={{ backgroundColor: color || '#6B7280' }}
            aria-hidden
        />
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

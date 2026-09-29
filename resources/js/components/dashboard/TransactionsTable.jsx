import { ArrowDown, ArrowUp, ArrowUpDown, BookmarkPlus, Pencil, Trash2 } from 'lucide-react';
import { formatDate, signedMoney } from '../../lib/format';
import Badge from '../ui/Badge';
import Card from '../ui/Card';
import EmptyState, { EMPTY_COPY } from '../ui/EmptyState';
import ErrorState from '../ui/ErrorState';
import Skeleton from '../ui/Skeleton';

/**
 * @param {{
 *   label: string,
 *   column: string,
 *   sort: string,
 *   direction: 'asc' | 'desc',
 *   onSort: (column: string) => void,
 *   className?: string,
 * }} props
 */
function SortableHeader({ label, column, sort, direction, onSort, className = '' }) {
    const active = sort === column;
    const Icon = !active ? ArrowUpDown : direction === 'asc' ? ArrowUp : ArrowDown;

    return (
        <th
            scope="col"
            className={[
                'sticky top-0 z-10 bg-surface px-4 py-3 text-left text-caption font-medium text-ink-secondary',
                className,
            ]
                .filter(Boolean)
                .join(' ')}
        >
            <button
                type="button"
                className={[
                    'inline-flex items-center gap-1.5 rounded-sm transition',
                    'hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                    active ? 'text-ink' : '',
                ]
                    .filter(Boolean)
                    .join(' ')}
                onClick={() => onSort(column)}
                aria-sort={active ? (direction === 'asc' ? 'ascending' : 'descending') : 'none'}
            >
                {label}
                <Icon size={14} strokeWidth={1.75} aria-hidden />
            </button>
        </th>
    );
}

/**
 * @param {{ category?: { name?: string, color?: string|null }|null }} props
 */
function CategoryCell({ category }) {
    if (!category?.name) {
        return <span className="text-ink-muted">—</span>;
    }

    return (
        <Badge tone="category" color={category.color} title={category.name}>
            {category.name}
        </Badge>
    );
}

function RowActions({
    row,
    canRememberAlias,
    onEdit,
    onDelete,
    onRememberAlias,
}) {
    const showEdit = Boolean(row.editable) && typeof onEdit === 'function';
    const showDelete = Boolean(row.deletable) && typeof onDelete === 'function';
    const showRemember = canRememberAlias && typeof onRememberAlias === 'function';

    if (!showEdit && !showDelete && !showRemember) {
        return <span className="text-ink-muted">—</span>;
    }

    return (
        <div className="flex items-center justify-end gap-1">
            {showEdit ? (
                <IconAction
                    label={`Editar ${row.description || 'lançamento'}`}
                    onClick={() => onEdit(row)}
                >
                    <Pencil size={16} strokeWidth={1.75} aria-hidden />
                </IconAction>
            ) : null}
            {showRemember ? (
                <IconAction
                    label={`Lembrar apelido de ${row.description || 'lançamento'}`}
                    onClick={() => onRememberAlias(row)}
                >
                    <BookmarkPlus size={16} strokeWidth={1.75} aria-hidden />
                </IconAction>
            ) : null}
            {showDelete ? (
                <IconAction
                    label={`Excluir ${row.description || 'lançamento'}`}
                    tone="danger"
                    onClick={() => onDelete(row)}
                >
                    <Trash2 size={16} strokeWidth={1.75} aria-hidden />
                </IconAction>
            ) : null}
        </div>
    );
}

function IconAction({ label, onClick, children, tone = 'default' }) {
    return (
        <button
            type="button"
            className={[
                'inline-flex h-9 w-9 items-center justify-center rounded-full transition',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                tone === 'danger'
                    ? 'text-ink-secondary hover:bg-surface-raised hover:text-feedback-danger'
                    : 'text-ink-secondary hover:bg-surface-raised hover:text-ink',
            ].join(' ')}
            aria-label={label}
            title={label}
            onClick={onClick}
        >
            {children}
        </button>
    );
}

function TableHeader({ sort, direction, onSort, showActions }) {
    return (
        <thead>
            <tr className="border-b border-border-subtle">
                <SortableHeader
                    label="Data"
                    column="occurred_on"
                    sort={sort}
                    direction={direction}
                    onSort={onSort}
                    className="min-w-[7rem]"
                />
                <th
                    scope="col"
                    className="sticky top-0 z-10 bg-surface px-4 py-3 text-left text-caption font-medium text-ink-secondary"
                >
                    Descrição
                </th>
                <th
                    scope="col"
                    className="sticky top-0 z-10 bg-surface px-4 py-3 text-left text-caption font-medium text-ink-secondary"
                >
                    Categoria
                </th>
                <th
                    scope="col"
                    className="sticky top-0 z-10 bg-surface px-4 py-3 text-left text-caption font-medium text-ink-secondary"
                >
                    Tipo
                </th>
                <SortableHeader
                    label="Valor"
                    column="amount"
                    sort={sort}
                    direction={direction}
                    onSort={onSort}
                    className="min-w-[8rem] text-right"
                />
                {showActions ? (
                    <th
                        scope="col"
                        className="sticky top-0 z-10 bg-surface px-4 py-3 text-right text-caption font-medium text-ink-secondary"
                    >
                        <span className="sr-only">Ações</span>
                    </th>
                ) : null}
            </tr>
        </thead>
    );
}

function TableEmptyState({
    hasTypeOrCategoryFilter,
    hasSearchQuery,
    looksEmptyAccount,
}) {
    if (hasSearchQuery && !hasTypeOrCategoryFilter) {
        return (
            <EmptyState
                title={EMPTY_COPY.search.title}
                description={EMPTY_COPY.search.description}
            />
        );
    }

    if (hasTypeOrCategoryFilter || hasSearchQuery) {
        return (
            <EmptyState
                title={EMPTY_COPY.filters.title}
                description={EMPTY_COPY.filters.description}
            />
        );
    }

    if (looksEmptyAccount) {
        return (
            <EmptyState
                title={EMPTY_COPY.account.title}
                description={EMPTY_COPY.account.description}
                action={EMPTY_COPY.account.action}
            />
        );
    }

    return (
        <EmptyState
            title={EMPTY_COPY.period.title}
            description={EMPTY_COPY.period.description}
            action={{ label: 'Importar extrato', to: '/upload', variant: 'secondary' }}
        />
    );
}

/**
 * TransactionsTable — Etapa D §4.7.1 / §4.7.3 / PLAN_EXPANSAO §8.2.
 *
 * @param {{
 *   rows?: Array<object>,
 *   sort?: string,
 *   direction?: 'asc'|'desc',
 *   onSortChange?: (next: { sort: string, direction: 'asc'|'desc' }) => void,
 *   status?: 'idle'|'loading'|'success'|'error',
 *   error?: string|null,
 *   onRetry?: () => void,
 *   hasTypeOrCategoryFilter?: boolean,
 *   hasSearchQuery?: boolean,
 *   looksEmptyAccount?: boolean,
 *   canRememberAlias?: boolean,
 *   onEdit?: (row: object) => void,
 *   onDelete?: (row: object) => void,
 *   onRememberAlias?: (row: object) => void,
 *   className?: string,
 * }} props
 */
export default function TransactionsTable({
    rows = [],
    sort = 'occurred_on',
    direction = 'desc',
    onSortChange,
    status = 'idle',
    error = null,
    onRetry,
    hasTypeOrCategoryFilter = false,
    hasSearchQuery = false,
    looksEmptyAccount = false,
    canRememberAlias = false,
    onEdit,
    onDelete,
    onRememberAlias,
    className = '',
}) {
    const showActions =
        typeof onEdit === 'function' ||
        typeof onDelete === 'function' ||
        (canRememberAlias && typeof onRememberAlias === 'function');

    const handleSort = (column) => {
        if (!onSortChange) {
            return;
        }

        if (sort === column) {
            onSortChange({
                sort: column,
                direction: direction === 'asc' ? 'desc' : 'asc',
            });
            return;
        }

        onSortChange({ sort: column, direction: 'desc' });
    };

    if (status === 'error') {
        return (
            <ErrorState
                title="Não foi possível carregar as movimentações"
                message={error || 'Tente novamente em instantes.'}
                onRetry={onRetry}
                className={className}
            />
        );
    }

    const showSkeleton = status === 'loading' && rows.length === 0;
    const showEmpty = status !== 'loading' && rows.length === 0;

    if (showSkeleton) {
        return (
            <div aria-busy="true" aria-label="Carregando movimentações" className={className}>
                <Skeleton.Table rows={8} />
            </div>
        );
    }

    return (
        <Card
            className={['overflow-hidden p-0', className].filter(Boolean).join(' ')}
            aria-busy={status === 'loading' || undefined}
        >
            {showEmpty ? (
                <TableEmptyState
                    hasTypeOrCategoryFilter={hasTypeOrCategoryFilter}
                    hasSearchQuery={hasSearchQuery}
                    looksEmptyAccount={looksEmptyAccount}
                />
            ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-[44rem] w-full border-collapse text-body text-ink">
                        <TableHeader
                            sort={sort}
                            direction={direction}
                            onSort={handleSort}
                            showActions={showActions}
                        />
                        <tbody className={status === 'loading' ? 'opacity-60' : undefined}>
                            {rows.map((row) => {
                                const isCredit = row.type === 'credit';

                                return (
                                    <tr
                                        key={row.id}
                                        className="border-b border-border-subtle/60 transition hover:bg-surface-raised"
                                    >
                                        <td className="whitespace-nowrap px-4 py-3 text-ink-secondary">
                                            {formatDate(row.occurred_on)}
                                        </td>
                                        <td className="max-w-[16rem] px-4 py-3">
                                            <span
                                                className="block truncate"
                                                title={row.description}
                                            >
                                                {row.description || '—'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <CategoryCell category={row.category} />
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge tone={isCredit ? 'positive' : 'danger'}>
                                                {isCredit ? 'Entrada' : 'Saída'}
                                            </Badge>
                                        </td>
                                        <td
                                            className={[
                                                'whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums',
                                                isCredit
                                                    ? 'text-feedback-positive'
                                                    : 'text-feedback-danger',
                                            ].join(' ')}
                                        >
                                            {signedMoney(row.type, row.amount)}
                                        </td>
                                        {showActions ? (
                                            <td className="whitespace-nowrap px-3 py-2 text-right">
                                                <RowActions
                                                    row={row}
                                                    canRememberAlias={canRememberAlias}
                                                    onEdit={onEdit}
                                                    onDelete={onDelete}
                                                    onRememberAlias={onRememberAlias}
                                                />
                                            </td>
                                        ) : null}
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}
        </Card>
    );
}

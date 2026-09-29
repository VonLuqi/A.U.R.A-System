import { Link } from 'react-router-dom';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import {
    GOAL_KIND_LABELS,
    GOAL_STATUS_LABELS,
} from '../../lib/goals';
import { formatDate, formatMoney } from '../../lib/format';
import Badge from '../ui/Badge';
import Button from '../ui/Button';
import EmptyState from '../ui/EmptyState';
import ErrorState from '../ui/ErrorState';
import Skeleton from '../ui/Skeleton';
import GoalProgressBar from './GoalProgressBar';

/**
 * GoalsPanel — lista CRUD completa (PLAN_EXPANSAO §8.5).
 *
 * @param {{
 *   goals?: Array<object>,
 *   meta?: { goals_used?: number, goals_remaining?: number|null }|null,
 *   status?: 'idle'|'loading'|'success'|'error',
 *   error?: string|null,
 *   onRetry?: () => void,
 *   onCreate?: () => void,
 *   onEdit?: (goal: object) => void,
 *   onDelete?: (goal: object) => void,
 * }} props
 */
export default function GoalsPanel({
    goals = [],
    meta = null,
    status = 'idle',
    error = null,
    onRetry,
    onCreate,
    onEdit,
    onDelete,
}) {
    if (status === 'error') {
        return (
            <ErrorState
                title="Não foi possível carregar as metas"
                message={error || 'Tente novamente em instantes.'}
                onRetry={onRetry}
            />
        );
    }

    if (status === 'loading' && goals.length === 0) {
        return (
            <div className="flex flex-col gap-3" aria-busy="true" aria-label="Carregando metas">
                <Skeleton.Table rows={4} />
            </div>
        );
    }

    if (goals.length === 0) {
        return (
            <EmptyState
                title="Nenhuma meta ainda"
                description="Crie a primeira meta de poupança ou amortização de dívida."
                action={
                    onCreate
                        ? { label: 'Criar primeira meta', onClick: onCreate }
                        : { label: 'Criar primeira meta', to: '/goals' }
                }
            />
        );
    }

    const remainingLabel =
        meta?.goals_remaining == null
            ? 'ilimitado'
            : `${meta.goals_remaining} restante${meta.goals_remaining === 1 ? '' : 's'}`;

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-caption text-ink-muted">
                    {meta?.goals_used != null
                        ? `${meta.goals_used} meta${meta.goals_used === 1 ? '' : 's'} · cota ${remainingLabel}`
                        : `${goals.length} meta${goals.length === 1 ? '' : 's'}`}
                </p>
                {onCreate ? (
                    <Button type="button" size="sm" onClick={onCreate}>
                        <Plus size={16} strokeWidth={2} aria-hidden />
                        Nova meta
                    </Button>
                ) : null}
            </div>

            <ul className="flex flex-col gap-3" aria-label="Lista de metas">
                {goals.map((goal) => {
                    const percent = Number(goal.progress_percent) || 0;
                    const isDebt = goal.kind === 'debt_payoff';
                    const tone =
                        goal.status === 'completed'
                            ? 'positive'
                            : goal.status === 'cancelled'
                              ? 'danger'
                              : 'brand';

                    return (
                        <li
                            key={goal.id}
                            className="rounded-xl border border-border-subtle bg-surface px-4 py-4 sm:px-5"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h3 className="truncate text-body-lg font-semibold text-ink">
                                            {goal.name}
                                        </h3>
                                        <Badge tone="meta">
                                            {GOAL_KIND_LABELS[goal.kind] ?? goal.kind}
                                        </Badge>
                                        <Badge
                                            tone={
                                                goal.status === 'completed'
                                                    ? 'positive'
                                                    : goal.status === 'cancelled'
                                                      ? 'danger'
                                                      : 'meta'
                                            }
                                        >
                                            {GOAL_STATUS_LABELS[goal.status] ?? goal.status}
                                        </Badge>
                                    </div>
                                    <p className="mt-1 text-caption text-ink-secondary">
                                        {formatMoney(goal.current_amount)} de{' '}
                                        {formatMoney(goal.target_amount)}
                                        {goal.deadline_on
                                            ? ` · prazo ${formatDate(goal.deadline_on)}`
                                            : ''}
                                        {isDebt ? ' · dívida' : ''}
                                    </p>
                                </div>

                                <div className="flex shrink-0 items-center gap-1">
                                    {onEdit ? (
                                        <IconAction
                                            label={`Editar ${goal.name}`}
                                            onClick={() => onEdit(goal)}
                                        >
                                            <Pencil size={16} strokeWidth={1.75} aria-hidden />
                                        </IconAction>
                                    ) : null}
                                    {onDelete ? (
                                        <IconAction
                                            label={`Excluir ${goal.name}`}
                                            tone="danger"
                                            onClick={() => onDelete(goal)}
                                        >
                                            <Trash2 size={16} strokeWidth={1.75} aria-hidden />
                                        </IconAction>
                                    ) : null}
                                </div>
                            </div>

                            <div className="mt-3">
                                <GoalProgressBar
                                    percent={percent}
                                    tone={tone}
                                    label={`${Math.round(percent)}% · falta ${formatMoney(goal.remaining_amount)}`}
                                />
                            </div>
                        </li>
                    );
                })}
            </ul>

            <div className="pt-1">
                <Link
                    to="/dashboard"
                    className="text-caption font-medium text-ink-secondary no-underline transition hover:text-ink"
                >
                    Voltar ao dashboard
                </Link>
            </div>
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

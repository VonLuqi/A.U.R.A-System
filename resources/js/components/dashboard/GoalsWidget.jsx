import { Link } from 'react-router-dom';
import { ArrowRight } from 'lucide-react';
import { GOAL_KIND_LABELS } from '../../lib/goals';
import { formatMoney } from '../../lib/format';
import { cx } from '../../lib/cx';
import Button from '../ui/Button';
import Card from '../ui/Card';
import GoalProgressBar from '../goals/GoalProgressBar';
import Skeleton from '../ui/Skeleton';

const MAX_VISIBLE = 3;

/**
 * GoalsWidget — card lateral compacto no dashboard (PLAN_EXPANSAO §8.5).
 *
 * @param {{
 *   goals?: {
 *     items?: Array<object>,
 *     active_count?: number,
 *     cards?: object,
 *   }|null,
 *   loading?: boolean,
 *   onCreate?: () => void,
 *   className?: string,
 * }} props
 */
export default function GoalsWidget({
    goals = null,
    loading = false,
    onCreate,
    className = '',
}) {
    const items = Array.isArray(goals?.items) ? goals.items : [];
    const active = items.filter((item) => item.status === 'active');
    const visible = active.slice(0, MAX_VISIBLE);
    const moreCount = Math.max(0, active.length - visible.length);

    if (loading && !goals) {
        return (
            <Card
                className={cx('flex h-full flex-col gap-3', className)}
                aria-busy="true"
                aria-label="Carregando metas"
            >
                <h2 className="text-h2 font-semibold text-ink">Metas</h2>
                <div className="flex flex-col gap-2">
                    {[0, 1, 2].map((key) => (
                        <Skeleton key={key} className="h-12 w-full" radius="lg" />
                    ))}
                </div>
            </Card>
        );
    }

    return (
        <Card
            className={cx('flex h-full flex-col gap-3', className)}
            aria-label="Metas ativas"
        >
            <div className="flex items-center justify-between gap-2">
                <h2 className="text-h2 font-semibold text-ink">Metas</h2>
                <Link
                    to="/goals"
                    className="inline-flex items-center gap-1 text-caption font-medium text-ink-secondary no-underline transition hover:text-ink"
                >
                    Ver todas
                    <ArrowRight size={14} strokeWidth={2} aria-hidden />
                </Link>
            </div>

            {visible.length === 0 ? (
                <div
                    className="flex flex-1 flex-col justify-center gap-3 py-2"
                    role="status"
                >
                    <div className="min-w-0">
                        <p className="text-body font-semibold text-ink">
                            Nenhuma meta ativa
                        </p>
                        <p className="mt-0.5 text-caption text-ink-muted">
                            Poupança e dívidas com progresso visual.
                        </p>
                    </div>
                    {onCreate ? (
                        <Button
                            type="button"
                            variant="primary"
                            size="sm"
                            className="w-full"
                            onClick={onCreate}
                        >
                            Criar meta
                        </Button>
                    ) : (
                        <Link
                            to="/goals"
                            className="inline-flex h-10 w-full items-center justify-center rounded-full bg-brand px-4 text-caption font-semibold text-ink-on-brand transition hover:brightness-95"
                        >
                            Criar meta
                        </Link>
                    )}
                </div>
            ) : (
                <ul className="flex flex-1 flex-col gap-2">
                    {visible.map((goal) => {
                        const percent = Number(goal.progress_percent) || 0;
                        const kindLabel = GOAL_KIND_LABELS[goal.kind] ?? goal.kind;

                        return (
                            <li
                                key={goal.id}
                                className="rounded-lg border border-border-subtle bg-surface-sunken/40 px-3 py-2.5"
                            >
                                <div className="flex items-baseline justify-between gap-2">
                                    <div className="min-w-0">
                                        <p className="truncate text-caption font-semibold text-ink">
                                            {goal.name}
                                        </p>
                                        <p className="mt-0.5 truncate text-small text-ink-muted">
                                            {kindLabel}
                                            {' · '}
                                            {formatMoney(goal.current_amount)} /{' '}
                                            {formatMoney(goal.target_amount)}
                                        </p>
                                    </div>
                                    <p className="shrink-0 text-small tabular-nums text-ink-secondary">
                                        {Math.round(percent)}%
                                    </p>
                                </div>
                                <GoalProgressBar
                                    className="mt-1.5"
                                    size="sm"
                                    percent={percent}
                                    tone={percent >= 100 ? 'positive' : 'brand'}
                                />
                            </li>
                        );
                    })}
                </ul>
            )}

            {moreCount > 0 ? (
                <p className="text-small text-ink-muted">
                    +{moreCount} —{' '}
                    <Link to="/goals" className="font-medium text-ink-secondary hover:text-ink">
                        ver todas
                    </Link>
                </p>
            ) : null}
        </Card>
    );
}

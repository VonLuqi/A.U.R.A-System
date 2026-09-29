import { Link } from 'react-router-dom';
import { ArrowRight } from 'lucide-react';
import { GOAL_KIND_LABELS } from '../../lib/goals';
import { formatMoney } from '../../lib/format';
import EmptyState from '../ui/EmptyState';
import GoalProgressBar from '../goals/GoalProgressBar';
import Skeleton from '../ui/Skeleton';

const MAX_VISIBLE = 4;

/**
 * GoalsWidget — lista compacta no dashboard (PLAN_EXPANSAO §8.5).
 *
 * @param {{
 *   goals?: {
 *     items?: Array<object>,
 *     active_count?: number,
 *     cards?: object,
 *   }|null,
 *   loading?: boolean,
 *   onCreate?: () => void,
 * }} props
 */
export default function GoalsWidget({ goals = null, loading = false, onCreate }) {
    const items = Array.isArray(goals?.items) ? goals.items : [];
    const active = items.filter((item) => item.status === 'active');
    const visible = active.slice(0, MAX_VISIBLE);
    const moreCount = Math.max(0, active.length - visible.length);

    if (loading && !goals) {
        return (
            <section
                className="flex flex-col gap-3"
                aria-busy="true"
                aria-label="Carregando metas"
            >
                <div className="flex items-center justify-between gap-3">
                    <h2 className="text-h2 font-semibold text-ink">Metas</h2>
                </div>
                <Skeleton.Table rows={3} />
            </section>
        );
    }

    return (
        <section className="flex flex-col gap-3" aria-label="Metas ativas">
            <div className="flex flex-wrap items-center justify-between gap-3">
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
                <EmptyState
                    title="Nenhuma meta ativa"
                    description="Acompanhe poupança e amortização de dívidas com progresso visual."
                    action={
                        onCreate
                            ? { label: 'Criar primeira meta', onClick: onCreate }
                            : { label: 'Criar primeira meta', to: '/goals' }
                    }
                />
            ) : (
                <ul className="flex flex-col gap-2">
                    {visible.map((goal) => {
                        const percent = Number(goal.progress_percent) || 0;

                        return (
                            <li
                                key={goal.id}
                                className="rounded-xl border border-border-subtle bg-surface px-4 py-3"
                            >
                                <div className="flex items-baseline justify-between gap-3">
                                    <p className="truncate text-body font-semibold text-ink">
                                        {goal.name}
                                    </p>
                                    <p className="shrink-0 text-caption tabular-nums text-ink-secondary">
                                        {Math.round(percent)}%
                                    </p>
                                </div>
                                <p className="mt-0.5 text-small text-ink-muted">
                                    {GOAL_KIND_LABELS[goal.kind] ?? goal.kind}
                                    {' · '}
                                    {formatMoney(goal.current_amount)} /{' '}
                                    {formatMoney(goal.target_amount)}
                                </p>
                                <GoalProgressBar
                                    className="mt-2"
                                    percent={percent}
                                    tone={percent >= 100 ? 'positive' : 'brand'}
                                />
                            </li>
                        );
                    })}
                </ul>
            )}

            {moreCount > 0 ? (
                <p className="text-caption text-ink-muted">
                    +{moreCount} meta{moreCount === 1 ? '' : 's'} ativa
                    {moreCount === 1 ? '' : 's'} —{' '}
                    <Link to="/goals" className="font-medium text-ink-secondary hover:text-ink">
                        ver na página de metas
                    </Link>
                </p>
            ) : null}
        </section>
    );
}

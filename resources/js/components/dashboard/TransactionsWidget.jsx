import { Link } from 'react-router-dom';
import { ArrowDownLeft, ArrowRight, ArrowUpRight } from 'lucide-react';
import { formatDate, signedMoney } from '../../lib/format';
import { cx } from '../../lib/cx';
import Badge from '../ui/Badge';
import Button from '../ui/Button';
import Card from '../ui/Card';
import Skeleton from '../ui/Skeleton';

const MAX_VISIBLE = 6;

/**
 * @param {{ type?: string, amount?: string|number|null }} props
 */
function AmountBadge({ type, amount }) {
    const isCredit = type === 'credit';
    const isDebit = type === 'debit';
    const Icon = isCredit ? ArrowUpRight : ArrowDownLeft;

    return (
        <span
            className={cx(
                'inline-flex shrink-0 items-center gap-1 rounded-lg px-2.5 py-1',
                'text-body font-bold tabular-nums tracking-tight',
                isCredit && 'bg-feedback-positive/15 text-feedback-positive',
                isDebit && 'bg-feedback-danger/15 text-feedback-danger',
                !isCredit && !isDebit && 'bg-surface-raised text-ink',
            )}
            title={isCredit ? 'Entrada' : isDebit ? 'Saída' : 'Valor'}
        >
            {(isCredit || isDebit) ? (
                <Icon size={14} strokeWidth={2.25} aria-hidden className="opacity-90" />
            ) : null}
            <span>{signedMoney(type, amount)}</span>
        </span>
    );
}

/**
 * TransactionsWidget — resumo das últimas movimentações no dashboard.
 *
 * @param {{
 *   rows?: Array<object>,
 *   total?: number,
 *   loading?: boolean,
 *   error?: string|null,
 *   onRetry?: () => void,
 *   onCreate?: () => void,
 *   className?: string,
 * }} props
 */
export default function TransactionsWidget({
    rows = [],
    total = 0,
    loading = false,
    error = null,
    onRetry,
    onCreate,
    className = '',
}) {
    const list = Array.isArray(rows) ? rows : [];
    const visible = list.slice(0, MAX_VISIBLE);
    const moreCount = Math.max(0, (Number(total) || list.length) - visible.length);

    if (loading && list.length === 0 && !error) {
        return (
            <Card
                className={cx('flex flex-col gap-3', className)}
                aria-busy="true"
                aria-label="Carregando movimentações"
            >
                <h2 className="text-h2 font-semibold text-ink">Movimentações</h2>
                <div className="flex flex-col gap-2">
                    {[0, 1, 2, 3].map((key) => (
                        <Skeleton key={key} className="h-12 w-full" radius="lg" />
                    ))}
                </div>
            </Card>
        );
    }

    return (
        <Card
            className={cx('flex flex-col gap-3', className, loading && 'opacity-60')}
            aria-label="Movimentações recentes"
            aria-busy={loading || undefined}
        >
            <div className="flex items-center justify-between gap-2">
                <h2 className="text-h2 font-semibold text-ink">Movimentações</h2>
                <Link
                    to="/transactions"
                    className="inline-flex items-center gap-1 text-caption font-medium text-ink-secondary no-underline transition hover:text-ink"
                >
                    Ver todas
                    <ArrowRight size={14} strokeWidth={2} aria-hidden />
                </Link>
            </div>

            {error ? (
                <div className="flex flex-col gap-2 py-2" role="alert">
                    <p className="text-body text-ink-secondary">
                        {error || 'Não foi possível carregar as movimentações.'}
                    </p>
                    {onRetry ? (
                        <Button type="button" variant="secondary" size="sm" onClick={onRetry}>
                            Tentar de novo
                        </Button>
                    ) : null}
                </div>
            ) : null}

            {!error && visible.length === 0 ? (
                <div
                    className="flex flex-1 flex-col justify-center gap-3 py-2"
                    role="status"
                >
                    <div className="min-w-0">
                        <p className="text-body font-semibold text-ink">
                            Nenhuma movimentação no período
                        </p>
                        <p className="mt-0.5 text-caption text-ink-muted">
                            Importe um extrato ou lance uma transação manual.
                        </p>
                    </div>
                    {onCreate ? (
                        <Button
                            type="button"
                            variant="primary"
                            size="sm"
                            className="w-full sm:w-auto"
                            onClick={onCreate}
                        >
                            Nova transação
                        </Button>
                    ) : (
                        <Link
                            to="/transactions"
                            className="inline-flex h-10 w-full items-center justify-center rounded-full bg-brand px-4 text-caption font-semibold text-ink-on-brand transition hover:brightness-95 sm:w-auto"
                        >
                            Ver movimentações
                        </Link>
                    )}
                </div>
            ) : null}

            {!error && visible.length > 0 ? (
                <ul className="flex flex-col gap-1.5">
                    {visible.map((row) => (
                        <li
                            key={row.id}
                            className="flex items-center justify-between gap-3 rounded-lg border border-border-subtle bg-surface-sunken/40 px-3 py-2.5"
                        >
                            <div className="min-w-0 flex-1">
                                <p
                                    className="truncate text-caption font-semibold text-ink"
                                    title={row.description}
                                >
                                    {row.description || 'Sem descrição'}
                                </p>
                                <div className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <span className="text-small text-ink-muted">
                                        {formatDate(row.occurred_on)}
                                    </span>
                                    {row.category?.name ? (
                                        <Badge
                                            tone="category"
                                            color={row.category.color}
                                            title={row.category.name}
                                        >
                                            {row.category.name}
                                        </Badge>
                                    ) : null}
                                    {row.credit_card?.name ? (
                                        <Badge tone="meta" title={row.credit_card.name}>
                                            {row.credit_card.name}
                                        </Badge>
                                    ) : null}
                                    {row.loan?.debtor_name ? (
                                        <Badge tone="meta" title={row.loan.debtor_name}>
                                            {row.loan.debtor_name}
                                        </Badge>
                                    ) : null}
                                </div>
                            </div>
                            <AmountBadge type={row.type} amount={row.amount} />
                        </li>
                    ))}
                </ul>
            ) : null}

            {!error && moreCount > 0 ? (
                <p className="text-small text-ink-muted">
                    +{moreCount} no período —{' '}
                    <Link
                        to="/transactions"
                        className="font-medium text-ink-secondary no-underline hover:text-ink"
                    >
                        ver todas
                    </Link>
                </p>
            ) : null}
        </Card>
    );
}

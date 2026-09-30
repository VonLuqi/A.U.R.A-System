import { Link } from 'react-router-dom';
import { CreditCard, Users } from 'lucide-react';
import { formatMoney } from '../../lib/format';
import Skeleton from '../ui/Skeleton';
import MetricCard from './MetricCard';

/**
 * HubMetricCards — cartões e cobranças no topo do dashboard.
 *
 * @param {{
 *   hub?: {
 *     credit_cards?: { active_count?: number, period_spend?: string|number },
 *     loans?: {
 *       open_count?: number,
 *       remaining_total?: string|number,
 *       overdue_count?: number,
 *       debtors_with_open?: number,
 *     },
 *   }|null,
 *   showCards?: boolean,
 *   showLoans?: boolean,
 *   loading?: boolean,
 * }} props
 */
export default function HubMetricCards({
    hub = null,
    showCards = false,
    showLoans = false,
    loading = false,
}) {
    if (!showCards && !showLoans) {
        return null;
    }

    const colClass =
        showCards && showLoans
            ? 'grid grid-cols-1 gap-5 sm:grid-cols-2'
            : 'grid grid-cols-1 gap-5';

    if (loading && !hub) {
        return (
            <div className={colClass} aria-busy="true" aria-label="Carregando resumos">
                {showCards ? <Skeleton.Metric /> : null}
                {showLoans ? <Skeleton.Metric /> : null}
            </div>
        );
    }

    const cards = hub?.credit_cards;
    const loans = hub?.loans;
    const overdue = Number(loans?.overdue_count ?? 0);
    const openCount = Number(loans?.open_count ?? 0);
    const debtors = Number(loans?.debtors_with_open ?? 0);
    const activeCards = Number(cards?.active_count ?? 0);

    const cardsHint =
        activeCards === 0
            ? 'Nenhum cartão ativo'
            : `${activeCards} cartão${activeCards === 1 ? '' : 'ões'} ativo${activeCards === 1 ? '' : 's'}`;

    let loansHint = 'Nenhuma cobrança em aberto';
    if (openCount > 0) {
        const base = `${openCount} em aberto · ${debtors} pessoa${debtors === 1 ? '' : 's'}`;
        loansHint = overdue > 0
            ? `${base} · ${overdue} atrasada${overdue === 1 ? '' : 's'}`
            : base;
    }

    return (
        <div
            className={[colClass, loading ? 'opacity-60' : ''].filter(Boolean).join(' ')}
            aria-busy={loading || undefined}
            aria-label="Resumos de cartões e cobranças"
        >
            {showCards ? (
                <Link to="/cards" className="block no-underline transition hover:opacity-90">
                    <MetricCard
                        label="Cartões no período"
                        value={formatMoney(cards?.period_spend)}
                        tone="danger"
                        icon={<CreditCard size={18} strokeWidth={1.75} />}
                        hint={cardsHint}
                    />
                </Link>
            ) : null}
            {showLoans ? (
                <Link to="/loans" className="block no-underline transition hover:opacity-90">
                    <MetricCard
                        label="A cobrar"
                        value={formatMoney(loans?.remaining_total)}
                        tone={overdue > 0 ? 'danger' : 'primary'}
                        icon={<Users size={18} strokeWidth={1.75} />}
                        hint={loansHint}
                    />
                </Link>
            ) : null}
        </div>
    );
}

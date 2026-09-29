import { ArrowUpRight, TrendingDown } from 'lucide-react';
import { formatMoney } from '../../lib/format';
import Skeleton from '../ui/Skeleton';
import MetricCard from './MetricCard';

function parseAmount(value) {
    if (value === null || value === undefined || value === '') {
        return 0;
    }

    const amount = typeof value === 'number' ? value : Number.parseFloat(String(value).replace(',', '.'));

    return Number.isNaN(amount) ? 0 : amount;
}

/**
 * MetricCards — Etapa D §4.4.1.
 * Grid responsiva gap 20px (space-5).
 *
 * @param {{
 *   cards?: {
 *     total_income?: string|number,
 *     total_expense?: string|number,
 *     balance?: string|number,
 *     transactions_count?: string|number,
 *   }|null,
 *   loading?: boolean,
 * }} props
 */
export default function MetricCards({ cards = null, loading = false }) {
    if (loading && !cards) {
        return (
            <div
                className="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4"
                aria-busy="true"
                aria-label="Carregando métricas"
            >
                {Array.from({ length: 4 }).map((_, index) => (
                    <Skeleton.Metric key={index} />
                ))}
            </div>
        );
    }

    const balance = parseAmount(cards?.balance);
    const balanceTone = balance < 0 ? 'danger' : balance > 0 ? 'positive' : 'primary';

    return (
        <div
            className={[
                'grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4',
                loading ? 'opacity-60' : '',
            ]
                .filter(Boolean)
                .join(' ')}
            aria-busy={loading || undefined}
        >
            <MetricCard
                label="Entradas"
                value={formatMoney(cards?.total_income)}
                tone="positive"
                icon={<ArrowUpRight size={18} strokeWidth={1.75} />}
            />
            <MetricCard
                label="Saídas"
                value={formatMoney(cards?.total_expense)}
                tone="danger"
                icon={<TrendingDown size={18} strokeWidth={1.75} />}
            />
            <MetricCard
                label="Saldo"
                value={formatMoney(cards?.balance)}
                tone={balanceTone}
            />
            <MetricCard
                label="Transações"
                value={Number(cards?.transactions_count ?? 0).toLocaleString('pt-BR')}
                tone="primary"
            />
        </div>
    );
}

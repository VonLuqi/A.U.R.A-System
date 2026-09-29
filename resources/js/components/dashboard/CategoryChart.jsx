import {
    Bar,
    BarChart,
    Cell,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { formatMoney } from '../../lib/format';
import Card from '../ui/Card';
import EmptyState, { EMPTY_COPY } from '../ui/EmptyState';
import ErrorState from '../ui/ErrorState';
import Skeleton from '../ui/Skeleton';

const AXIS_COLOR = 'var(--color-text-secondary)';
const FALLBACK_FILL = 'var(--color-brand-muted)';
const CREDIT_FILL = 'var(--color-feedback-positive, #A8E6C3)';

function parseAmount(value) {
    if (value === null || value === undefined || value === '') {
        return 0;
    }

    const amount = typeof value === 'number' ? value : Number.parseFloat(String(value).replace(',', '.'));

    return Number.isNaN(amount) ? 0 : amount;
}

function typeLabel(type) {
    if (type === 'credit') {
        return 'Entrada';
    }
    if (type === 'debit') {
        return 'Saída';
    }

    return null;
}

function chartTitle(filterType) {
    if (filterType === 'credit') {
        return 'Entradas por categoria';
    }
    if (filterType === 'debit') {
        return 'Despesas por categoria';
    }

    return 'Por categoria';
}

/**
 * @param {Array<{
 *   category_id?: number|null,
 *   name?: string|null,
 *   color?: string|null,
 *   total?: string|number,
 *   count?: number,
 *   type?: string,
 * }>|null|undefined} rows
 */
function toChartData(rows) {
    if (!Array.isArray(rows)) {
        return [];
    }

    const nameCounts = new Map();
    for (const row of rows) {
        const base = row.name || 'Sem categoria';
        nameCounts.set(base, (nameCounts.get(base) || 0) + 1);
    }

    return rows.map((row, index) => {
        const baseName = row.name || 'Sem categoria';
        const suffix = typeLabel(row.type);
        const needsSuffix = nameCounts.get(baseName) > 1 && suffix;
        const fill =
            row.type === 'credit'
                ? row.color || CREDIT_FILL
                : row.color || FALLBACK_FILL;

        return {
            key: `${row.category_id ?? `uncategorized-${index}`}-${row.type ?? 'all'}`,
            name: needsSuffix ? `${baseName} · ${suffix}` : baseName,
            color: fill,
            total: parseAmount(row.total),
            count: Number(row.count) || 0,
            type: row.type || '',
            typeLabel: suffix,
        };
    });
}

function ChartTooltip({ active, payload }) {
    if (!active || !payload?.length) {
        return null;
    }

    const row = payload[0]?.payload;

    if (!row) {
        return null;
    }

    return (
        <div className="rounded-sm border border-border-subtle bg-surface-inverse px-3 py-2 text-small text-ink-on-inverse shadow-sm">
            <p className="mb-1 flex items-center gap-2 font-medium">
                <span
                    aria-hidden
                    className="inline-block size-2 rounded-full"
                    style={{ backgroundColor: row.color }}
                />
                {row.name}
            </p>
            {row.typeLabel ? (
                <p className="text-ink-on-inverse/70">{row.typeLabel}</p>
            ) : null}
            <p>{formatMoney(row.total)}</p>
            <p className="text-ink-on-inverse/70">
                {row.count} {row.count === 1 ? 'movimentação' : 'movimentações'}
            </p>
        </div>
    );
}

/**
 * CategoryChart — Etapa D §4.6.2 / §5.3.4.
 *
 * @param {{
 *   byCategory?: Array<{
 *     category_id?: number|null,
 *     name?: string|null,
 *     color?: string|null,
 *     total?: string|number,
 *     count?: number,
 *     type?: string,
 *   }>|null,
 *   filterType?: '' | 'credit' | 'debit',
 *   loading?: boolean,
 *   error?: string|null,
 *   onRetry?: () => void,
 *   className?: string,
 * }} props
 */
export default function CategoryChart({
    byCategory = null,
    filterType = '',
    loading = false,
    error = null,
    onRetry,
    className = '',
}) {
    const data = toChartData(byCategory);
    const isEmpty = !loading && !error && data.length === 0;
    const chartHeight = Math.max(280, data.length * 36 + 48);

    return (
        <Card className={['flex flex-col gap-4', className].filter(Boolean).join(' ')}>
            <h2 className="text-h2 font-semibold text-ink">{chartTitle(filterType)}</h2>

            {error ? (
                <ErrorState
                    title="Não foi possível carregar o gráfico"
                    message={error}
                    onRetry={onRetry}
                    className="border-0 bg-transparent p-0"
                />
            ) : loading && data.length === 0 ? (
                <Skeleton.Chart bare />
            ) : isEmpty ? (
                <EmptyState
                    title={EMPTY_COPY.chart.title}
                    description={EMPTY_COPY.chart.description}
                />
            ) : (
                <div
                    className={[
                        'w-full rounded-lg bg-surface-sunken/40 pt-2',
                        loading ? 'opacity-60' : '',
                    ]
                        .filter(Boolean)
                        .join(' ')}
                    aria-busy={loading || undefined}
                >
                    <ResponsiveContainer width="100%" height={chartHeight}>
                        <BarChart
                            layout="vertical"
                            data={data}
                            margin={{ top: 8, right: 16, left: 8, bottom: 8 }}
                        >
                            <XAxis
                                type="number"
                                tick={{ fill: AXIS_COLOR, fontSize: 12 }}
                                axisLine={false}
                                tickLine={false}
                                tickFormatter={(value) =>
                                    new Intl.NumberFormat('pt-BR', {
                                        notation: 'compact',
                                        compactDisplay: 'short',
                                    }).format(value)
                                }
                            />
                            <YAxis
                                type="category"
                                dataKey="name"
                                width={108}
                                tick={{ fill: AXIS_COLOR, fontSize: 12 }}
                                axisLine={false}
                                tickLine={false}
                            />
                            <Tooltip
                                cursor={{ fill: 'var(--color-surface-raised)', opacity: 0.45 }}
                                content={<ChartTooltip />}
                            />
                            <Bar dataKey="total" name="Total" radius={[0, 4, 4, 0]} maxBarSize={22}>
                                {data.map((entry) => (
                                    <Cell key={entry.key} fill={entry.color} />
                                ))}
                            </Bar>
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            )}
        </Card>
    );
}

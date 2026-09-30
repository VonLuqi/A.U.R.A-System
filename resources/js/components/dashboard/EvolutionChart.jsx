import { useState } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { formatMoney, formatPeriod } from '../../lib/format';
import { cx } from '../../lib/cx';
import Card from '../ui/Card';
import EmptyState, { EMPTY_COPY } from '../ui/EmptyState';
import ErrorState from '../ui/ErrorState';
import Pill from '../ui/Pill';
import Skeleton from '../ui/Skeleton';

const INCOME_FILL = 'var(--color-feedback-positive)';
const EXPENSE_FILL = 'var(--color-brand-primary)';
const AXIS_COLOR = 'var(--color-text-secondary)';
const GRID_COLOR = 'var(--color-border-subtle)';

const CHART_MODES = [
    { id: 'bars', label: 'Barras' },
    { id: 'lines', label: 'Linhas' },
];

function parseAmount(value) {
    if (value === null || value === undefined || value === '') {
        return 0;
    }

    const amount = typeof value === 'number' ? value : Number.parseFloat(String(value).replace(',', '.'));

    return Number.isNaN(amount) ? 0 : amount;
}

/**
 * @param {Array<{ period: string, income: string|number, expense: string|number }>|null|undefined} series
 */
function toChartData(series) {
    if (!Array.isArray(series)) {
        return [];
    }

    return series.map((row) => ({
        period: row.period,
        label: formatPeriod(row.period),
        income: parseAmount(row.income),
        expense: parseAmount(row.expense),
    }));
}

function ChartTooltip({ active, payload, label }) {
    if (!active || !payload?.length) {
        return null;
    }

    return (
        <div className="rounded-sm border border-border-subtle bg-surface-inverse px-3 py-2 text-small text-ink-on-inverse shadow-sm">
            <p className="mb-1 font-medium">{label}</p>
            {payload.map((entry) => (
                <p key={entry.dataKey} className="flex items-center gap-2">
                    <span
                        aria-hidden
                        className="inline-block size-2 rounded-full"
                        style={{ backgroundColor: entry.color }}
                    />
                    <span>
                        {entry.dataKey === 'income' ? 'Entradas' : 'Saídas'}:{' '}
                        {formatMoney(entry.value)}
                    </span>
                </p>
            ))}
        </div>
    );
}

function compactTick(value) {
    return new Intl.NumberFormat('pt-BR', {
        notation: 'compact',
        compactDisplay: 'short',
    }).format(value);
}

/**
 * EvolutionChart — Etapa D §4.6.1 / §5.3.4.
 * Alterna barras ↔ linhas no mesmo card (tooltip/hover interativo).
 *
 * @param {{
 *   series?: Array<{ period: string, income: string|number, expense: string|number, balance?: string|number }>|null,
 *   loading?: boolean,
 *   error?: string|null,
 *   onRetry?: () => void,
 *   className?: string,
 * }} props
 */
export default function EvolutionChart({
    series = null,
    loading = false,
    error = null,
    onRetry,
    className = '',
}) {
    const [mode, setMode] = useState(/** @type {'bars'|'lines'} */ ('bars'));
    const data = toChartData(series);
    const isEmpty = !loading && !error && data.length === 0;
    const showChart = !error && !isEmpty && !(loading && data.length === 0);

    return (
        <Card className={['flex flex-col gap-4', className].filter(Boolean).join(' ')}>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-h2 font-semibold text-ink">Evolução no período</h2>
                {showChart ? (
                    <div
                        className="flex flex-wrap items-center gap-1.5"
                        role="group"
                        aria-label="Tipo de gráfico"
                    >
                        {CHART_MODES.map((option) => (
                            <Pill
                                key={option.id}
                                active={mode === option.id}
                                className="min-h-8 px-2.5 py-1 text-small"
                                onClick={() => setMode(option.id)}
                            >
                                {option.label}
                            </Pill>
                        ))}
                    </div>
                ) : null}
            </div>

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
                    className={cx(
                        'w-full rounded-lg bg-surface-sunken/40 pt-2',
                        loading && 'opacity-60',
                    )}
                    aria-busy={loading || undefined}
                >
                    <ResponsiveContainer width="100%" height={280}>
                        {mode === 'lines' ? (
                            <LineChart
                                data={data}
                                margin={{ top: 8, right: 8, left: 0, bottom: 0 }}
                            >
                                <CartesianGrid
                                    stroke={GRID_COLOR}
                                    strokeDasharray="3 3"
                                    vertical={false}
                                />
                                <XAxis
                                    dataKey="label"
                                    tick={{ fill: AXIS_COLOR, fontSize: 12 }}
                                    axisLine={{ stroke: GRID_COLOR }}
                                    tickLine={false}
                                />
                                <YAxis
                                    tick={{ fill: AXIS_COLOR, fontSize: 12 }}
                                    axisLine={false}
                                    tickLine={false}
                                    width={56}
                                    tickFormatter={compactTick}
                                />
                                <Tooltip
                                    cursor={{
                                        stroke: 'var(--color-border-default)',
                                        strokeDasharray: '4 4',
                                    }}
                                    content={<ChartTooltip />}
                                />
                                <Line
                                    type="monotone"
                                    dataKey="income"
                                    name="Entradas"
                                    stroke={INCOME_FILL}
                                    strokeWidth={2.25}
                                    dot={{
                                        r: 3.5,
                                        fill: INCOME_FILL,
                                        strokeWidth: 0,
                                    }}
                                    activeDot={{
                                        r: 6,
                                        strokeWidth: 2,
                                        stroke: 'var(--color-surface-default)',
                                        fill: INCOME_FILL,
                                    }}
                                />
                                <Line
                                    type="monotone"
                                    dataKey="expense"
                                    name="Saídas"
                                    stroke={EXPENSE_FILL}
                                    strokeWidth={2.25}
                                    dot={{
                                        r: 3.5,
                                        fill: EXPENSE_FILL,
                                        strokeWidth: 0,
                                    }}
                                    activeDot={{
                                        r: 6,
                                        strokeWidth: 2,
                                        stroke: 'var(--color-surface-default)',
                                        fill: EXPENSE_FILL,
                                    }}
                                />
                            </LineChart>
                        ) : (
                            <BarChart
                                data={data}
                                margin={{ top: 8, right: 8, left: 0, bottom: 0 }}
                            >
                                <CartesianGrid
                                    stroke={GRID_COLOR}
                                    strokeDasharray="3 3"
                                    vertical={false}
                                />
                                <XAxis
                                    dataKey="label"
                                    tick={{ fill: AXIS_COLOR, fontSize: 12 }}
                                    axisLine={{ stroke: GRID_COLOR }}
                                    tickLine={false}
                                />
                                <YAxis
                                    tick={{ fill: AXIS_COLOR, fontSize: 12 }}
                                    axisLine={false}
                                    tickLine={false}
                                    width={56}
                                    tickFormatter={compactTick}
                                />
                                <Tooltip
                                    cursor={{
                                        fill: 'var(--color-surface-raised)',
                                        opacity: 0.45,
                                    }}
                                    content={<ChartTooltip />}
                                />
                                <Bar
                                    dataKey="income"
                                    name="Entradas"
                                    fill={INCOME_FILL}
                                    radius={[4, 4, 0, 0]}
                                    maxBarSize={28}
                                />
                                <Bar
                                    dataKey="expense"
                                    name="Saídas"
                                    fill={EXPENSE_FILL}
                                    radius={[4, 4, 0, 0]}
                                    maxBarSize={28}
                                />
                            </BarChart>
                        )}
                    </ResponsiveContainer>
                </div>
            )}
        </Card>
    );
}

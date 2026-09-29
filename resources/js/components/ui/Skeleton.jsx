/**
 * Skeleton — Etapa D §5.3.1.
 * Bloco `surface.raised` + pulse CSS (opacity). Variantes compostas.
 *
 * @param {{
 *   className?: string,
 *   radius?: 'sm'|'md'|'lg'|'xl'|'full',
 * }} props
 */
function Skeleton({ className = '', radius = 'md' }) {
    const radiusClass = {
        sm: 'rounded-sm',
        md: 'rounded-md',
        lg: 'rounded-lg',
        xl: 'rounded-xl',
        full: 'rounded-full',
    }[radius] ?? 'rounded-md';

    return (
        <div
            aria-hidden
            className={['aura-skeleton', radiusClass, className].filter(Boolean).join(' ')}
        />
    );
}

/** Card de métrica (label + valor). */
function Metric({ className = '' }) {
    return (
        <div
            className={[
                'rounded-xl border border-border-subtle bg-surface p-6',
                className,
            ]
                .filter(Boolean)
                .join(' ')}
            aria-hidden
        >
            <Skeleton className="mb-4 h-3 w-20" radius="sm" />
            <Skeleton className="h-9 w-32" radius="md" />
        </div>
    );
}

/** Área de gráfico (~240px) com título placeholder. */
function Chart({ className = '', heightClass = 'h-[240px]', bare = false }) {
    if (bare) {
        return (
            <Skeleton
                className={['w-full', heightClass, className].filter(Boolean).join(' ')}
                radius="lg"
            />
        );
    }

    return (
        <div
            className={[
                'flex flex-col gap-4 rounded-xl border border-border-subtle bg-surface p-6',
                className,
            ]
                .filter(Boolean)
                .join(' ')}
            aria-hidden
        >
            <Skeleton className="h-5 w-40" radius="sm" />
            <Skeleton className={['w-full', heightClass].join(' ')} radius="lg" />
        </div>
    );
}

/** N linhas de tabela (default 8). */
function Table({ rows = 8, className = '' }) {
    return (
        <div
            className={[
                'overflow-hidden rounded-xl border border-border-subtle bg-surface',
                className,
            ]
                .filter(Boolean)
                .join(' ')}
            aria-hidden
        >
            <div className="border-b border-border-subtle px-4 py-3">
                <div className="flex gap-4">
                    <Skeleton className="h-3 w-16" radius="sm" />
                    <Skeleton className="h-3 w-28" radius="sm" />
                    <Skeleton className="h-3 w-24" radius="sm" />
                    <Skeleton className="h-3 w-14" radius="sm" />
                    <Skeleton className="ml-auto h-3 w-20" radius="sm" />
                </div>
            </div>
            {Array.from({ length: rows }).map((_, index) => (
                <div
                    key={index}
                    className="flex items-center gap-4 border-b border-border-subtle/60 px-4 py-3 last:border-b-0"
                >
                    <Skeleton className="h-4 w-20 shrink-0" radius="sm" />
                    <Skeleton className="h-4 max-w-[40%] flex-1" radius="sm" />
                    <Skeleton className="h-4 w-24 shrink-0" radius="sm" />
                    <Skeleton className="h-6 w-16 shrink-0" radius="full" />
                    <Skeleton className="ml-auto h-4 w-24 shrink-0" radius="sm" />
                </div>
            ))}
        </div>
    );
}

/** Placeholder compacto da TopNav (mobile/desktop). */
function Nav({ className = '' }) {
    return (
        <div
            className={[
                'flex h-14 items-center justify-between gap-4 border-b border-border-subtle px-6',
                className,
            ]
                .filter(Boolean)
                .join(' ')}
            aria-hidden
        >
            <Skeleton className="h-7 w-28" radius="md" />
            <div className="hidden items-center gap-3 sm:flex">
                <Skeleton className="h-4 w-20" radius="sm" />
                <Skeleton className="h-4 w-16" radius="sm" />
                <Skeleton className="h-8 w-8" radius="full" />
            </div>
            <Skeleton className="h-8 w-8 sm:hidden" radius="md" />
        </div>
    );
}

Skeleton.Metric = Metric;
Skeleton.Chart = Chart;
Skeleton.Table = Table;
Skeleton.Nav = Nav;

export default Skeleton;

import Pill from '../ui/Pill';

const PILL_THRESHOLD = 9;

/**
 * @param {{ color?: string, className?: string }} props
 */
function ColorSwatch({ color, className = '' }) {
    if (!color) {
        return null;
    }

    return (
        <span
            aria-hidden
            className={[
                'inline-block size-2.5 shrink-0 rounded-full border border-border',
                className,
            ]
                .filter(Boolean)
                .join(' ')}
            style={{ backgroundColor: color }}
        />
    );
}

/**
 * CategorySelect — Etapa D §4.5.3.
 * Pills se ≤ ~9 categorias; select nativo caso contrário.
 *
 * @param {{
 *   categories: Array<{ id: number, name: string, slug?: string, type?: string, color?: string }>,
 *   value: '' | number,
 *   onChange: (categoryId: '' | number) => void,
 *   loading?: boolean,
 *   className?: string,
 * }} props
 */
export default function CategorySelect({
    categories = [],
    value = '',
    onChange,
    loading = false,
    className = '',
}) {
    if (loading && categories.length === 0) {
        return (
            <p className={['text-caption text-ink-muted', className].filter(Boolean).join(' ')}>
                Carregando categorias…
            </p>
        );
    }

    if (categories.length === 0) {
        return null;
    }

    const usePills = categories.length <= PILL_THRESHOLD;

    if (usePills) {
        return (
            <div
                className={['flex flex-wrap gap-2', className].filter(Boolean).join(' ')}
                role="group"
                aria-label="Categoria"
            >
                <Pill
                    active={value === ''}
                    onClick={() => onChange('')}
                    className="shrink-0"
                >
                    Todas as categorias
                </Pill>
                {categories.map((category) => (
                    <Pill
                        key={category.id}
                        active={value === category.id}
                        onClick={() => onChange(category.id)}
                        className="shrink-0 gap-2"
                    >
                        <ColorSwatch color={category.color} />
                        {category.name}
                    </Pill>
                ))}
            </div>
        );
    }

    const selected = categories.find((c) => c.id === value);

    return (
        <div
            className={['flex min-w-[12rem] max-w-xs items-center gap-2', className]
                .filter(Boolean)
                .join(' ')}
        >
            <ColorSwatch color={selected?.color} className="size-3" />
            <label className="sr-only" htmlFor="dashboard-category-select">
                Categoria
            </label>
            <select
                id="dashboard-category-select"
                value={value === '' ? '' : String(value)}
                onChange={(event) => {
                    const next = event.target.value;
                    onChange(next === '' ? '' : Number(next));
                }}
                className={[
                    'h-11 w-full rounded-lg border border-border bg-surface-sunken px-3 font-sans text-body text-ink',
                    'outline-none transition-[border-color,box-shadow]',
                    'focus-visible:border-brand focus-visible:ring-1 focus-visible:ring-brand',
                ].join(' ')}
            >
                <option value="">Todas as categorias</option>
                {categories.map((category) => (
                    <option key={category.id} value={category.id}>
                        {category.name}
                    </option>
                ))}
            </select>
        </div>
    );
}

import { Search, X } from 'lucide-react';
import Input from '../ui/Input';

/**
 * SearchField — Etapa D §4.5.4.
 *
 * @param {{
 *   value?: string,
 *   onChange: (q: string) => void,
 *   className?: string,
 *   id?: string,
 * }} props
 */
export default function SearchField({
    value = '',
    onChange,
    className = '',
    id = 'dashboard-search',
}) {
    const hasValue = value.length > 0;

    return (
        <div className={['relative w-full max-w-sm', className].filter(Boolean).join(' ')}>
            <label className="sr-only" htmlFor={id}>
                Buscar na descrição
            </label>
            <Search
                size={18}
                strokeWidth={1.75}
                aria-hidden
                className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted"
            />
            <Input
                id={id}
                type="search"
                value={value}
                maxLength={120}
                placeholder="Buscar na descrição…"
                autoComplete="off"
                onChange={(event) => onChange(event.target.value)}
                className={['pl-10', hasValue ? 'pr-10' : ''].filter(Boolean).join(' ')}
            />
            {hasValue ? (
                <button
                    type="button"
                    aria-label="Limpar busca"
                    className={[
                        'absolute right-2 top-1/2 -translate-y-1/2',
                        'inline-flex size-7 items-center justify-center rounded-md text-ink-muted',
                        'transition hover:bg-surface-raised hover:text-ink',
                        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                    ].join(' ')}
                    onClick={() => onChange('')}
                >
                    <X size={16} strokeWidth={1.75} aria-hidden />
                </button>
            ) : null}
        </div>
    );
}

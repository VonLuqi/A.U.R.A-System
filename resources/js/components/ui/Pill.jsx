import { forwardRef } from 'react';
import { cx } from '../../lib/cx';

/**
 * Pill — Design System filters (Etapa D §5.4).
 * Ativo = inverse; inativo = border secondary.
 */
const Pill = forwardRef(function Pill(
    { children, active = false, type = 'button', className = '', ...props },
    ref,
) {
    return (
        <button
            ref={ref}
            type={type}
            aria-pressed={active}
            className={cx(
                'inline-flex min-h-10 items-center justify-center gap-2 rounded-full px-3 py-2 text-caption font-medium transition',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 focus-visible:ring-offset-canvas',
                active
                    ? 'bg-surface-inverse text-ink-on-inverse'
                    : 'border border-border bg-transparent text-ink hover:bg-surface-raised',
                className,
            )}
            {...props}
        >
            {children}
        </button>
    );
});

export default Pill;

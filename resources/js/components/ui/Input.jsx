import { forwardRef } from 'react';
import { cx } from '../../lib/cx';

/**
 * Input — Design System (Etapa D §5.4).
 * surface.sunken · border.default · texto primary · placeholder muted ·
 * focus ring brand suave (1px, sem glow exagerado).
 */
const Input = forwardRef(function Input(
    { className = '', invalid = false, ...props },
    ref,
) {
    return (
        <input
            ref={ref}
            aria-invalid={invalid || undefined}
            className={cx(
                'h-11 w-full rounded-lg border bg-surface-sunken px-3 font-sans text-body text-ink',
                'placeholder:text-ink-muted',
                'outline-none transition-[border-color,box-shadow]',
                'focus-visible:border-brand focus-visible:ring-1 focus-visible:ring-brand',
                'disabled:cursor-not-allowed disabled:opacity-60',
                invalid ? 'border-feedback-danger' : 'border-border',
                className,
            )}
            {...props}
        />
    );
});

export default Input;

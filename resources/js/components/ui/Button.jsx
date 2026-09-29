import { forwardRef } from 'react';
import { cx } from '../../lib/cx';
import Spinner from './Spinner';

const VARIANT_CLASS = {
    primary:
        'bg-brand text-ink-on-brand hover:brightness-95 focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 focus-visible:ring-offset-canvas',
    secondary:
        'border border-border bg-transparent text-ink hover:bg-surface-raised focus-visible:ring-2 focus-visible:ring-border focus-visible:ring-offset-2 focus-visible:ring-offset-canvas',
    inverse:
        'bg-surface-inverse text-ink-on-inverse focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 focus-visible:ring-offset-canvas',
    ghost:
        'bg-transparent text-ink-secondary hover:text-ink focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 focus-visible:ring-offset-canvas',
    danger:
        'bg-feedback-danger text-ink-on-brand focus-visible:ring-2 focus-visible:ring-feedback-danger focus-visible:ring-offset-2 focus-visible:ring-offset-canvas',
};

const SIZE_CLASS = {
    sm: 'min-h-10 h-10 px-4 text-caption',
    md: 'min-h-11 h-11 px-5 text-caption font-semibold',
};

const SPINNER_CLASS = {
    primary: 'border-ink-on-brand border-t-transparent',
    secondary: 'border-ink border-t-transparent',
    inverse: 'border-ink-on-inverse border-t-transparent',
    ghost: 'border-ink-secondary border-t-transparent',
    danger: 'border-ink-on-brand border-t-transparent',
};

/**
 * Button — Design System (Etapa D §5.4).
 * Variantes: primary · secondary · inverse · ghost · danger · sizes sm|md · loading.
 */
const Button = forwardRef(function Button(
    {
        children,
        className = '',
        variant = 'primary',
        size = 'md',
        loading = false,
        disabled = false,
        type = 'button',
        ...props
    },
    ref,
) {
    const resolvedVariant = VARIANT_CLASS[variant] ? variant : 'primary';

    return (
        <button
            ref={ref}
            type={type}
            disabled={disabled || loading}
            aria-busy={loading || undefined}
            className={cx(
                'inline-flex items-center justify-center gap-2 rounded-full font-medium transition',
                'disabled:cursor-not-allowed disabled:opacity-60',
                VARIANT_CLASS[resolvedVariant],
                SIZE_CLASS[size] ?? SIZE_CLASS.md,
                className,
            )}
            {...props}
        >
            {loading ? (
                <Spinner
                    size="sm"
                    className={SPINNER_CLASS[resolvedVariant] ?? SPINNER_CLASS.primary}
                />
            ) : null}
            {children}
        </button>
    );
});

export default Button;

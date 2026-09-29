import { forwardRef } from 'react';
import { cx } from '../../lib/cx';

const TONE_CLASS = {
    brand: 'bg-brand text-ink-on-brand',
    inverse: 'bg-surface-inverse text-ink-on-inverse',
    meta: 'border border-border-subtle bg-surface-raised text-ink',
    positive: 'border border-feedback-positive/30 bg-transparent text-feedback-positive',
    danger: 'border border-feedback-danger/30 bg-transparent text-feedback-danger',
    category: 'border border-border-subtle bg-surface-raised text-ink',
};

/**
 * Badge — Design System (Etapa D §5.4).
 * Tons: brand · meta · inverse · positive · danger · category (swatch opcional).
 *
 * @param {{
 *   children: import('react').ReactNode,
 *   className?: string,
 *   tone?: 'brand'|'meta'|'inverse'|'positive'|'danger'|'category',
 *   color?: string|null,
 * }} props
 */
const Badge = forwardRef(function Badge(
    { children, className = '', tone = 'meta', color = null, ...props },
    ref,
) {
    return (
        <span
            ref={ref}
            className={cx(
                'inline-flex max-w-full items-center gap-2 rounded-full px-2.5 py-1 text-caption font-medium',
                TONE_CLASS[tone] ?? TONE_CLASS.meta,
                className,
            )}
            {...props}
        >
            {tone === 'category' && color ? (
                <span
                    aria-hidden
                    className="inline-block size-2.5 shrink-0 rounded-full border border-border"
                    style={{ backgroundColor: color }}
                />
            ) : null}
            <span className="truncate">{children}</span>
        </span>
    );
});

export default Badge;

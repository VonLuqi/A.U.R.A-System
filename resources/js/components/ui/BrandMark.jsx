import { forwardRef } from 'react';
import { cx } from '../../lib/cx';

/**
 * BrandMark — Design System (Etapa D §5.4).
 * Accent brand + wordmark Aura. Sizes: sm | lg.
 */
const BrandMark = forwardRef(function BrandMark(
    { size = 'sm', className = '', ...props },
    ref,
) {
    const markSize = size === 'lg' ? 'h-4 w-4' : 'h-3 w-3';
    const wordmarkClass =
        size === 'lg' ? 'text-body-lg font-bold tracking-tight sm:text-h1' : 'text-body-lg font-bold';

    return (
        <div
            ref={ref}
            className={cx('inline-flex items-center gap-3', className)}
            {...props}
        >
            <span
                aria-hidden
                className={cx('inline-block shrink-0 rounded-full bg-brand', markSize)}
            />
            <span className={cx(wordmarkClass, 'text-ink')}>Aura</span>
        </div>
    );
});

export default BrandMark;

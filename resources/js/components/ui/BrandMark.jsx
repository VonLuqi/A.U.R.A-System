import { forwardRef } from 'react';
import { cx } from '../../lib/cx';
import AuraMark from './AuraMark';

/**
 * BrandMark — Design System §0.1 / Etapa I §4.2.
 * Mark SVG “aura abstrata” + wordmark tipográfico Aura.
 * Sizes: sm (nav) | lg (Login / splash). Prop opcional `animated` (§5).
 *
 * @param {{
 *   size?: 'sm'|'lg',
 *   animated?: boolean,
 *   className?: string,
 * }} props
 */
const BrandMark = forwardRef(function BrandMark(
    { size = 'sm', animated = false, className = '', ...props },
    ref,
) {
    const markSize = size === 'lg' ? 'h-10 w-10' : 'h-5 w-5';
    const wordmarkClass =
        size === 'lg' ? 'text-body-lg font-bold tracking-tight sm:text-h1' : 'text-body-lg font-bold';

    return (
        <div
            ref={ref}
            className={cx('inline-flex items-center gap-3', className)}
            {...props}
        >
            <AuraMark className={markSize} animated={animated} />
            <span className={cx(wordmarkClass, 'text-ink')}>Aura</span>
        </div>
    );
});

export default BrandMark;

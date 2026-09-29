import { forwardRef } from 'react';
import { cx } from '../../lib/cx';

/**
 * Card — Design System (Etapa D §5.4).
 * surface.default · border subtle · radius.xl · padding space.6
 */
const Card = forwardRef(function Card(
    { children, className = '', as: Component = 'div', ...props },
    ref,
) {
    return (
        <Component
            ref={ref}
            className={cx(
                'rounded-xl border border-border-subtle bg-surface p-6',
                className,
            )}
            {...props}
        >
            {children}
        </Component>
    );
});

export default Card;

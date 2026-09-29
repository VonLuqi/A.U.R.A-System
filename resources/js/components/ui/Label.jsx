import { forwardRef } from 'react';
import { cx } from '../../lib/cx';

/**
 * Label — Design System (Etapa D §5.4).
 */
const Label = forwardRef(function Label({ children, className = '', ...props }, ref) {
    return (
        <label
            ref={ref}
            className={cx('text-caption font-medium text-ink-secondary', className)}
            {...props}
        >
            {children}
        </label>
    );
});

export default Label;

import { forwardRef } from 'react';
import { cx } from '../../lib/cx';

/**
 * Spinner — Design System (Etapa D §5.4).
 */
const Spinner = forwardRef(function Spinner(
    { className = '', size = 'md', ...props },
    ref,
) {
    const sizeClass = {
        sm: 'h-4 w-4 border-2',
        md: 'h-8 w-8 border-2',
        lg: 'h-10 w-10 border-[3px]',
    }[size];

    return (
        <span
            ref={ref}
            role="status"
            aria-label="Carregando"
            className={cx(
                'inline-block animate-spin rounded-full border-brand border-t-transparent',
                sizeClass,
                className,
            )}
            {...props}
        />
    );
});

export default Spinner;

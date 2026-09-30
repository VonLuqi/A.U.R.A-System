import { forwardRef } from 'react';
import { cx } from '../../lib/cx';
import AuraMark from './AuraMark';

const SIZE_CLASS = {
    sm: 'h-12 w-12',
    md: 'h-16 w-16',
    lg: 'h-28 w-28',
};

const MARK_SIZE = {
    sm: 'h-6 w-6',
    md: 'h-8 w-8',
    lg: 'h-12 w-12',
};

/**
 * AuraLoader — Etapa I §5.1 / §5.4 (Instinto Superior / energia contida).
 * Loading global premium; não substitui Spinner de botões/tabelas.
 * Orçamento mobile: ≤3 camadas animadas (halo + core + mark); ring estático.
 *
 * @param {{
 *   size?: 'sm'|'md'|'lg',
 *   label?: string,
 *   className?: string,
 * }} props
 */
const AuraLoader = forwardRef(function AuraLoader(
    { size = 'md', label = 'Carregando', className = '', ...props },
    ref,
) {
    const box = SIZE_CLASS[size] ?? SIZE_CLASS.md;
    const mark = MARK_SIZE[size] ?? MARK_SIZE.md;

    return (
        <span
            ref={ref}
            role="status"
            aria-label={label}
            className={cx('aura-loader relative inline-flex items-center justify-center', box, className)}
            {...props}
        >
            {/* 1 — halo (aura-pulse) */}
            <span className="aura-glow-halo aura-ring absolute inset-0 rounded-full" aria-hidden />
            {/* 2 — core (aura-glow) */}
            <span className="aura-glow-core aura-ring absolute inset-[12%] rounded-full" aria-hidden />
            {/* ring estático — não conta no orçamento de animação */}
            <span className="aura-ring absolute inset-[28%] rounded-full border border-brand/30" aria-hidden />
            {/* 3 — mark (animate-aura-breathe) */}
            <AuraMark className={cx('relative z-10', mark)} animated />
            <span className="sr-only">{label}</span>
        </span>
    );
});

export default AuraLoader;

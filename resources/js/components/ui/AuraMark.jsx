import { BRAND_MARK } from '../../lib/brand';
import { cx } from '../../lib/cx';

/**
 * AuraMark — SVG canônico compartilhado (BrandMark / AuraLoader).
 * Spec: DESIGN-SYSTEM §0.1 · PLAN_PERFIL_BRANDING §4–§5.
 *
 * @param {{
 *   className?: string,
 *   animated?: boolean,
 * }} props
 */
export default function AuraMark({ className = '', animated = false }) {
    const { color, viewBox, opacity } = BRAND_MARK;

    return (
        <svg
            viewBox={viewBox}
            className={cx('shrink-0', animated && 'animate-aura-breathe', className)}
            aria-hidden
            focusable="false"
        >
            <circle cx="16" cy="16" r="3.5" fill={color} opacity={opacity.core} />
            <circle
                cx="16"
                cy="16"
                r="7"
                fill="none"
                stroke={color}
                strokeWidth="1.25"
                opacity={opacity.ringInner}
            />
            <circle
                cx="16"
                cy="16"
                r="11"
                fill="none"
                stroke={color}
                strokeWidth="1"
                opacity={opacity.ringOuter}
            />
            <ellipse
                cx="16"
                cy="16"
                rx="14.5"
                ry="12"
                fill="none"
                stroke={color}
                strokeWidth="0.75"
                opacity={opacity.halo}
                transform="rotate(-18 16 16)"
            />
        </svg>
    );
}

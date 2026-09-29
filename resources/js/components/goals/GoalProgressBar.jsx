import { cx } from '../../lib/cx';

const SIZE_CLASS = {
    sm: 'h-1.5',
    md: 'h-2',
};

/**
 * GoalProgressBar — barra de progresso (PLAN_EXPANSAO §8.5).
 *
 * @param {{
 *   percent?: number|null,
 *   tone?: 'brand'|'positive'|'danger',
 *   size?: 'sm'|'md',
 *   className?: string,
 *   label?: string,
 * }} props
 */
export default function GoalProgressBar({
    percent = 0,
    tone = 'brand',
    size = 'md',
    className = '',
    label,
}) {
    const safe = Math.max(0, Math.min(100, Number(percent) || 0));
    const fillClass =
        tone === 'positive'
            ? 'bg-feedback-positive'
            : tone === 'danger'
              ? 'bg-feedback-danger'
              : 'bg-brand';
    const heightClass = SIZE_CLASS[size] ?? SIZE_CLASS.md;

    return (
        <div className={cx('flex flex-col gap-1.5', className)}>
            <div
                className={cx(
                    'w-full overflow-hidden rounded-full bg-surface-sunken',
                    heightClass,
                )}
                role="progressbar"
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={Math.round(safe)}
                aria-label={label ?? `Progresso ${Math.round(safe)}%`}
            >
                <div
                    className={cx('h-full rounded-full transition-[width]', fillClass)}
                    style={{ width: `${safe}%` }}
                />
            </div>
            {label ? (
                <p className="text-small text-ink-muted">{label}</p>
            ) : null}
        </div>
    );
}

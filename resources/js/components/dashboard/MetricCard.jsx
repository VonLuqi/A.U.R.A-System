import Card from '../ui/Card';

const TONE_CLASS = {
    positive: 'text-feedback-positive',
    danger: 'text-feedback-danger',
    primary: 'text-ink',
    secondary: 'text-ink-secondary',
};

/**
 * MetricCard — Etapa D §4.4.1.
 *
 * @param {{
 *   label: string,
 *   value: string|number,
 *   tone?: 'positive'|'danger'|'primary'|'secondary',
 *   icon?: import('react').ReactNode,
 *   hint?: string|null,
 * }} props
 */
export default function MetricCard({ label, value, tone = 'primary', icon = null, hint = null }) {
    return (
        <Card className="relative flex flex-col gap-3">
            {icon ? (
                <span className="absolute right-6 top-6 text-ink-secondary" aria-hidden>
                    {icon}
                </span>
            ) : null}
            <p className="pr-8 text-caption font-medium text-ink-secondary">{label}</p>
            <p
                className={[
                    'text-display font-bold tracking-tight',
                    TONE_CLASS[tone] ?? TONE_CLASS.primary,
                ].join(' ')}
            >
                {value}
            </p>
            {hint ? (
                <p className="text-caption text-ink-muted">{hint}</p>
            ) : null}
        </Card>
    );
}

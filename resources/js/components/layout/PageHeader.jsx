/**
 * PageHeader — Etapa D §2.3.
 * title (H1 / heading-1 / 700) · description? (body / secondary) · actions? (slot)
 */
export default function PageHeader({ title, description, actions = null, className = '' }) {
    return (
        <header
            className={[
                'flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4',
                className,
            ]
                .filter(Boolean)
                .join(' ')}
        >
            <div className="min-w-0">
                <h1 className="text-h1 font-bold text-ink">{title}</h1>
                {description ? (
                    <p className="mt-2 text-body font-normal text-ink-secondary">{description}</p>
                ) : null}
            </div>
            {actions ? <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div> : null}
        </header>
    );
}

import { CircleAlert } from 'lucide-react';
import Button from './Button';

/**
 * ErrorState — Etapa D §5.3.3 / PLAN_EXPANSAO §8.4.
 * Ícone + título/mensagem + ações (retry / sugestão acionável).
 *
 * @param {{
 *   title?: string,
 *   message?: string,
 *   hint?: string,
 *   reference?: string|number,
 *   onRetry?: () => void,
 *   retryLabel?: string,
 *   secondaryAction?: { label: string, onClick: () => void }|null,
 *   className?: string,
 * }} props
 */
export default function ErrorState({
    title = 'Algo deu errado',
    message,
    hint,
    reference,
    onRetry,
    retryLabel = 'Tentar novamente',
    secondaryAction = null,
    className = '',
}) {
    return (
        <div
            className={[
                'flex flex-col items-start gap-3 rounded-xl border border-border-subtle bg-surface p-6',
                className,
            ]
                .filter(Boolean)
                .join(' ')}
            role="alert"
        >
            <div className="flex items-start gap-3">
                <CircleAlert
                    size={22}
                    strokeWidth={1.75}
                    className="mt-0.5 shrink-0 text-feedback-danger"
                    aria-hidden
                />
                <div className="min-w-0">
                    <h2 className="text-h3 font-semibold text-ink">{title}</h2>
                    {message ? (
                        <p className="mt-2 text-body text-ink-secondary">{message}</p>
                    ) : null}
                    {hint ? (
                        <p className="mt-2 text-caption text-ink-secondary">{hint}</p>
                    ) : null}
                    {reference !== undefined && reference !== null && reference !== '' ? (
                        <p className="mt-2 text-caption text-ink-muted">Ref. #{reference}</p>
                    ) : null}
                </div>
            </div>
            {onRetry || secondaryAction ? (
                <div className="flex flex-wrap items-center gap-2">
                    {secondaryAction ? (
                        <Button
                            type="button"
                            variant="primary"
                            size="sm"
                            onClick={secondaryAction.onClick}
                        >
                            {secondaryAction.label}
                        </Button>
                    ) : null}
                    {onRetry ? (
                        <Button type="button" variant="secondary" size="sm" onClick={onRetry}>
                            {retryLabel}
                        </Button>
                    ) : null}
                </div>
            ) : null}
        </div>
    );
}

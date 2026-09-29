import { useEffect, useId, useRef } from 'react';
import { X } from 'lucide-react';
import { cx } from '../../lib/cx';

/**
 * Modal — superfície de interação (PLAN_EXPANSAO §8.2).
 * Sem card decorativo; scrim + painel com tokens Aura.
 *
 * @param {{
 *   open: boolean,
 *   title: string,
 *   description?: string,
 *   onClose: () => void,
 *   children: import('react').ReactNode,
 *   footer?: import('react').ReactNode,
 *   size?: 'md'|'sm',
 *   closeOnScrim?: boolean,
 *   initialFocusRef?: import('react').RefObject<HTMLElement|null>,
 * }} props
 */
export default function Modal({
    open,
    title,
    description,
    onClose,
    children,
    footer = null,
    size = 'md',
    closeOnScrim = true,
    initialFocusRef,
}) {
    const titleId = useId();
    const descriptionId = useId();
    const panelRef = useRef(null);
    const previouslyFocused = useRef(null);

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        previouslyFocused.current = document.activeElement;

        const focusTarget =
            initialFocusRef?.current ??
            panelRef.current?.querySelector(
                'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
            );

        if (focusTarget && typeof focusTarget.focus === 'function') {
            focusTarget.focus();
        }

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        function onKeyDown(event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                onClose();
            }
        }

        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('keydown', onKeyDown);
            document.body.style.overflow = previousOverflow;
            if (
                previouslyFocused.current &&
                typeof previouslyFocused.current.focus === 'function'
            ) {
                previouslyFocused.current.focus();
            }
        };
    }, [open, onClose, initialFocusRef]);

    if (!open) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-6">
            <button
                type="button"
                className="absolute inset-0 bg-overlay-scrim"
                aria-label="Fechar"
                onClick={() => {
                    if (closeOnScrim) {
                        onClose();
                    }
                }}
            />
            <div
                ref={panelRef}
                role="dialog"
                aria-modal="true"
                aria-labelledby={titleId}
                aria-describedby={description ? descriptionId : undefined}
                className={cx(
                    'relative z-10 flex max-h-[min(90dvh,40rem)] w-full flex-col overflow-hidden',
                    'rounded-t-2xl border border-border bg-surface shadow-none sm:rounded-2xl',
                    size === 'sm' ? 'sm:max-w-md' : 'sm:max-w-lg',
                )}
            >
                <header className="flex shrink-0 items-start justify-between gap-3 border-b border-border-subtle px-5 py-4 sm:px-6">
                    <div className="min-w-0">
                        <h2 id={titleId} className="text-h2 font-semibold text-ink">
                            {title}
                        </h2>
                        {description ? (
                            <p
                                id={descriptionId}
                                className="mt-1 text-caption text-ink-secondary"
                            >
                                {description}
                            </p>
                        ) : null}
                    </div>
                    <button
                        type="button"
                        className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-ink-secondary transition hover:bg-surface-raised hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                        aria-label="Fechar"
                        onClick={onClose}
                    >
                        <X size={18} strokeWidth={1.75} aria-hidden />
                    </button>
                </header>

                <div className="min-h-0 flex-1 overflow-y-auto px-5 py-4 sm:px-6">
                    {children}
                </div>

                {footer ? (
                    <footer className="flex shrink-0 flex-wrap items-center justify-end gap-2 border-t border-border-subtle px-5 py-4 sm:px-6">
                        {footer}
                    </footer>
                ) : null}
            </div>
        </div>
    );
}

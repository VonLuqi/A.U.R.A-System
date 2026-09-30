import { useCallback, useEffect, useId, useMemo, useRef, useState } from 'react';
import { Bell } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import {
    useMarkAllNotificationsRead,
    useMarkNotificationRead,
    useNotifications,
} from '../../hooks/useNotifications';
import { useUnreadNotificationCount } from '../../hooks/useUnreadNotificationCount';
import { ABILITIES, can, featureEnabled } from '../../lib/auth';
import { cx } from '../../lib/cx';
import { formatRelativeTime } from '../../lib/format';

/**
 * @param {import('../../api/notifications').AppNotification} notification
 * @returns {string}
 */
function notificationTitle(notification) {
    const data = notification.data ?? {};

    if (notification.type === 'credit_card_due') {
        return data.name ? `Fatura ${data.name}` : 'Cartão';
    }

    if (notification.type === 'loan_due') {
        return data.debtor_name ? `Cobrar ${data.debtor_name}` : 'Cobrança';
    }

    return 'Aviso';
}

/**
 * @param {import('../../api/notifications').AppNotification} notification
 * @returns {string}
 */
function notificationMessage(notification) {
    const data = notification.data ?? {};

    if (typeof data.message === 'string' && data.message.trim()) {
        return data.message.trim();
    }

    return notificationTitle(notification);
}

/**
 * @param {import('../../api/notifications').AppNotification} notification
 * @returns {string}
 */
function notificationHref(notification) {
    const data = notification.data ?? {};

    if (notification.type === 'loan_due' && data.loan_id != null) {
        return `/loans?loan=${data.loan_id}`;
    }

    if (notification.type === 'credit_card_due') {
        return '/cards';
    }

    return '/dashboard';
}

/**
 * NotificationBell — PLAN_CARTOES_EMPRESTIMOS §6.6.
 */
export default function NotificationBell() {
    const { user } = useAuth();
    const navigate = useNavigate();
    const panelId = useId();
    const rootRef = useRef(null);
    const [open, setOpen] = useState(false);

    const enabled =
        featureEnabled(user, 'notifications') && can(user, ABILITIES.notificationsRead);

    const unread = useUnreadNotificationCount({ enabled });
    const listFilters = useMemo(
        () => ({ limit: 10, enabled: enabled && open }),
        [enabled, open],
    );
    const notifications = useNotifications(listFilters);

    const refreshAll = useCallback(async () => {
        await Promise.all([unread.refetch(), notifications.refetch()]);
    }, [unread.refetch, notifications.refetch]);

    const markRead = useMarkNotificationRead({ onSuccess: refreshAll });
    const markAllRead = useMarkAllNotificationsRead({ onSuccess: refreshAll });

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        function onKeyDown(event) {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        }

        function onPointerDown(event) {
            if (!rootRef.current?.contains(event.target)) {
                setOpen(false);
            }
        }

        document.addEventListener('keydown', onKeyDown);
        document.addEventListener('mousedown', onPointerDown);

        return () => {
            document.removeEventListener('keydown', onKeyDown);
            document.removeEventListener('mousedown', onPointerDown);
        };
    }, [open]);

    if (!enabled) {
        return null;
    }

    const count = unread.count;
    const badgeLabel = count > 9 ? '9+' : String(count);

    async function handleItemClick(notification) {
        if (!notification.read_at) {
            try {
                await markRead.mutate(notification.id);
            } catch {
                // toast already shown by hook
            }
        }

        setOpen(false);
        navigate(notificationHref(notification));
    }

    return (
        <div className="relative" ref={rootRef}>
            <button
                type="button"
                className={cx(
                    'relative inline-flex h-10 w-10 items-center justify-center rounded-full border border-border text-ink transition',
                    'hover:bg-surface-raised',
                    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 focus-visible:ring-offset-canvas',
                )}
                aria-label={
                    count > 0
                        ? `Notificações, ${count} não lida${count === 1 ? '' : 's'}`
                        : 'Notificações'
                }
                aria-haspopup="dialog"
                aria-expanded={open}
                aria-controls={panelId}
                onClick={() => setOpen((prev) => !prev)}
            >
                <Bell size={18} strokeWidth={1.75} aria-hidden />
                {count > 0 ? (
                    <span className="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-brand px-1 text-[10px] font-semibold text-ink-on-brand">
                        {badgeLabel}
                    </span>
                ) : null}
            </button>

            {open ? (
                <div
                    id={panelId}
                    role="dialog"
                    aria-label="Notificações recentes"
                    className={cx(
                        'absolute right-0 z-30 mt-2 w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-xl border border-border-subtle bg-surface shadow-lg',
                    )}
                >
                    <div className="flex items-center justify-between gap-2 border-b border-border-subtle px-3 py-2.5">
                        <p className="text-caption font-semibold text-ink">Avisos</p>
                        {count > 0 ? (
                            <button
                                type="button"
                                className={cx(
                                    'text-small font-medium text-ink-secondary transition hover:text-ink',
                                    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                                    'disabled:cursor-not-allowed disabled:opacity-60',
                                )}
                                disabled={markAllRead.isLoading}
                                onClick={() => {
                                    void markAllRead.mutate();
                                }}
                            >
                                Marcar todas como lidas
                            </button>
                        ) : null}
                    </div>

                    {notifications.status === 'loading' && notifications.data.length === 0 ? (
                        <p className="px-3 py-6 text-center text-caption text-ink-muted">
                            Carregando…
                        </p>
                    ) : null}

                    {notifications.status === 'error' ? (
                        <div className="flex flex-col items-center gap-2 px-3 py-6 text-center">
                            <p className="text-caption text-feedback-danger">
                                {notifications.error || 'Não foi possível carregar.'}
                            </p>
                            <button
                                type="button"
                                className="text-small font-medium text-ink underline-offset-2 hover:underline"
                                onClick={() => {
                                    void notifications.refetch();
                                }}
                            >
                                Tentar de novo
                            </button>
                        </div>
                    ) : null}

                    {notifications.status !== 'error'
                    && notifications.status !== 'loading'
                    && notifications.data.length === 0 ? (
                        <p className="px-3 py-8 text-center text-caption text-ink-muted" role="status">
                            Nenhum aviso no momento
                        </p>
                    ) : null}

                    {notifications.data.length > 0 ? (
                        <ul className="max-h-[min(24rem,60dvh)] overflow-y-auto">
                            {notifications.data.map((item) => {
                                const unreadItem = !item.read_at;

                                return (
                                    <li key={item.id} className="border-b border-border-subtle/60 last:border-b-0">
                                        <button
                                            type="button"
                                            className={cx(
                                                'flex w-full flex-col gap-0.5 px-3 py-2.5 text-left transition',
                                                'hover:bg-surface-raised',
                                                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand',
                                                unreadItem && 'bg-brand/5',
                                            )}
                                            onClick={() => {
                                                void handleItemClick(item);
                                            }}
                                        >
                                            <span
                                                className={cx(
                                                    'text-caption text-ink',
                                                    unreadItem ? 'font-semibold' : 'font-medium',
                                                )}
                                            >
                                                {notificationTitle(item)}
                                            </span>
                                            <span className="line-clamp-2 text-small text-ink-secondary">
                                                {notificationMessage(item)}
                                            </span>
                                            <span className="text-small text-ink-muted">
                                                {formatRelativeTime(item.created_at)}
                                            </span>
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    ) : null}
                </div>
            ) : null}
        </div>
    );
}

import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import {
    listNotifications,
    markAllNotificationsRead,
    markNotificationRead,
} from '../api/notifications';
import { getErrorMessage } from '../lib/errors';
import { TOAST_DURATION } from '../lib/toast';

/**
 * useNotifications — lista recente (PLAN_CARTOES_EMPRESTIMOS §6.2).
 *
 * @param {{
 *   unread?: boolean|0|1|string,
 *   limit?: number,
 *   enabled?: boolean,
 * }|null} [filters]
 */
export function useNotifications(filters = null) {
    const [data, setData] = useState([]);
    const [status, setStatus] = useState('idle');
    const [error, setError] = useState(null);

    const enabled = filters?.enabled !== false;
    const paramsKey = JSON.stringify({
        unread: filters?.unread,
        limit: filters?.limit,
        enabled,
    });

    const refetch = useCallback(async () => {
        if (!enabled) {
            return;
        }

        setStatus('loading');
        setError(null);

        try {
            const { unread, limit } = filters ?? {};
            const result = await listNotifications({ unread, limit });
            setData(result.data ?? []);
            setStatus('success');
        } catch (err) {
            setError(getErrorMessage(err));
            setStatus('error');
        }
    }, [enabled, filters, paramsKey]);

    useEffect(() => {
        if (!enabled) {
            setData([]);
            setStatus('idle');
            setError(null);
            return undefined;
        }

        let ignore = false;
        const controller = new AbortController();

        (async () => {
            setStatus('loading');
            setError(null);

            try {
                const { unread, limit } = filters ?? {};
                const result = await listNotifications(
                    { unread, limit },
                    { signal: controller.signal },
                );

                if (!ignore) {
                    setData(result.data ?? []);
                    setStatus('success');
                }
            } catch (err) {
                if (ignore || err?.code === 'ERR_CANCELED' || err?.name === 'CanceledError') {
                    return;
                }

                setError(getErrorMessage(err));
                setStatus('error');
            }
        })();

        return () => {
            ignore = true;
            controller.abort();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [paramsKey]);

    return {
        data,
        status,
        error,
        refetch,
    };
}

/**
 * @param {{ onSuccess?: (notification: object) => void|Promise<void> }} [options]
 */
export function useMarkNotificationRead({ onSuccess } = {}) {
    const [status, setStatus] = useState('idle');

    const mutate = useCallback(
        async (id) => {
            setStatus('loading');

            try {
                const data = await markNotificationRead(id);
                await onSuccess?.(data);
                setStatus('success');
                return data;
            } catch (error) {
                setStatus('error');
                toast.error(getErrorMessage(error), { duration: TOAST_DURATION });
                throw error;
            }
        },
        [onSuccess],
    );

    return { mutate, status, isLoading: status === 'loading' };
}

/**
 * @param {{ onSuccess?: (result: { marked: number }) => void|Promise<void> }} [options]
 */
export function useMarkAllNotificationsRead({ onSuccess } = {}) {
    const [status, setStatus] = useState('idle');

    const mutate = useCallback(
        async () => {
            setStatus('loading');

            try {
                const data = await markAllNotificationsRead();
                if ((data?.marked ?? 0) > 0) {
                    toast.success('Notificações marcadas como lidas.', {
                        duration: TOAST_DURATION,
                    });
                }
                await onSuccess?.(data);
                setStatus('success');
                return data;
            } catch (error) {
                setStatus('error');
                toast.error(getErrorMessage(error), { duration: TOAST_DURATION });
                throw error;
            }
        },
        [onSuccess],
    );

    return { mutate, status, isLoading: status === 'loading' };
}

import { useCallback, useEffect, useState } from 'react';
import { getUnreadNotificationCount } from '../api/notifications';
import { getErrorMessage } from '../lib/errors';

const POLL_MS = 60_000;

/**
 * useUnreadNotificationCount — poll 60s + refetch on focus
 * (PLAN_CARTOES_EMPRESTIMOS §6.2).
 *
 * @param {{ enabled?: boolean }} [options]
 */
export function useUnreadNotificationCount({ enabled = true } = {}) {
    const [count, setCount] = useState(0);
    const [status, setStatus] = useState('idle');
    const [error, setError] = useState(null);

    const refetch = useCallback(async () => {
        if (!enabled) {
            return 0;
        }

        setStatus((prev) => (prev === 'success' ? prev : 'loading'));
        setError(null);

        try {
            const next = await getUnreadNotificationCount();
            setCount(next);
            setStatus('success');
            return next;
        } catch (err) {
            setError(getErrorMessage(err));
            setStatus('error');
            return count;
        }
    }, [enabled, count]);

    useEffect(() => {
        if (!enabled) {
            setCount(0);
            setStatus('idle');
            setError(null);
            return undefined;
        }

        let ignore = false;
        const controller = new AbortController();

        const load = async () => {
            try {
                const next = await getUnreadNotificationCount({
                    signal: controller.signal,
                });

                if (!ignore) {
                    setCount(next);
                    setStatus('success');
                    setError(null);
                }
            } catch (err) {
                if (ignore || err?.code === 'ERR_CANCELED' || err?.name === 'CanceledError') {
                    return;
                }

                if (!ignore) {
                    setError(getErrorMessage(err));
                    setStatus('error');
                }
            }
        };

        setStatus('loading');
        load();

        const intervalId = window.setInterval(() => {
            if (document.visibilityState === 'visible') {
                load();
            }
        }, POLL_MS);

        const onFocus = () => {
            load();
        };

        const onVisibility = () => {
            if (document.visibilityState === 'visible') {
                load();
            }
        };

        window.addEventListener('focus', onFocus);
        document.addEventListener('visibilitychange', onVisibility);

        return () => {
            ignore = true;
            controller.abort();
            window.clearInterval(intervalId);
            window.removeEventListener('focus', onFocus);
            document.removeEventListener('visibilitychange', onVisibility);
        };
    }, [enabled]);

    return {
        count,
        status,
        error,
        refetch,
    };
}

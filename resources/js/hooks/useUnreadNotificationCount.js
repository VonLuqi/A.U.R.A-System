import { useCallback, useEffect, useRef, useState } from 'react';
import { getUnreadNotificationCount } from '../api/notifications';
import {
    getErrorMessage,
    getHttpStatus,
    getRetryAfterSeconds,
    RATE_LIMIT_RETRY_CAP_SECONDS,
} from '../lib/errors';

const POLL_MS = 60_000;

/**
 * useUnreadNotificationCount — poll 60s + refetch on focus
 * (PLAN_CARTOES_EMPRESTIMOS §6.2). Backoff on 429 until Retry-After.
 *
 * @param {{ enabled?: boolean }} [options]
 */
export function useUnreadNotificationCount({ enabled = true } = {}) {
    const [count, setCount] = useState(0);
    const [status, setStatus] = useState('idle');
    const [error, setError] = useState(null);
    /** @type {import('react').MutableRefObject<number>} */
    const quietUntilRef = useRef(0);

    const refetch = useCallback(async () => {
        if (!enabled) {
            return 0;
        }

        if (Date.now() < quietUntilRef.current) {
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
            if (getHttpStatus(err) === 429) {
                const retry = getRetryAfterSeconds(err);
                const wait = Math.min(
                    retry != null && retry > 0 ? retry : 60,
                    RATE_LIMIT_RETRY_CAP_SECONDS,
                );
                quietUntilRef.current = Date.now() + wait * 1000;
            }

            setError(getErrorMessage(err));
            setStatus('error');
            return 0;
        }
    }, [enabled]);

    useEffect(() => {
        if (!enabled) {
            setCount(0);
            setStatus('idle');
            setError(null);
            quietUntilRef.current = 0;
            return undefined;
        }

        let ignore = false;
        const controller = new AbortController();

        const load = async () => {
            if (Date.now() < quietUntilRef.current) {
                return;
            }

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
                    if (getHttpStatus(err) === 429) {
                        const retry = getRetryAfterSeconds(err);
                        const wait = Math.min(
                            retry != null && retry > 0 ? retry : 60,
                            RATE_LIMIT_RETRY_CAP_SECONDS,
                        );
                        quietUntilRef.current = Date.now() + wait * 1000;
                    }

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

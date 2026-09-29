import { useCallback, useEffect, useState } from 'react';
import { listUsers } from '../api/users';
import { getErrorMessage } from '../lib/errors';

/**
 * useUsers — lista admin (PLAN_EXPANSAO §8.6).
 *
 * @param {{
 *   role?: string,
 *   is_active?: boolean|0|1|string,
 *   q?: string,
 *   page?: number,
 *   per_page?: number,
 * }|null} [filters]
 */
export function useUsers(filters = null) {
    const [data, setData] = useState([]);
    const [meta, setMeta] = useState(null);
    const [status, setStatus] = useState('idle');
    const [error, setError] = useState(null);

    const paramsKey = JSON.stringify(filters ?? {});

    const refetch = useCallback(async () => {
        setStatus('loading');
        setError(null);

        try {
            const result = await listUsers(filters ?? {});
            setData(result.data ?? []);
            setMeta(result.meta ?? null);
            setStatus('success');
        } catch (err) {
            setError(getErrorMessage(err));
            setStatus('error');
        }
    }, [filters, paramsKey]);

    useEffect(() => {
        let ignore = false;
        const controller = new AbortController();

        (async () => {
            setStatus('loading');
            setError(null);

            try {
                const result = await listUsers(filters ?? {}, { signal: controller.signal });

                if (!ignore) {
                    setData(result.data ?? []);
                    setMeta(result.meta ?? null);
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
        meta,
        status,
        error,
        refetch,
    };
}

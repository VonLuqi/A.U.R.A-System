import { useCallback, useEffect, useState } from 'react';
import { listGoals } from '../api/goals';
import { getErrorMessage } from '../lib/errors';

/**
 * useGoals — lista paginada (PLAN_EXPANSAO §8.5).
 *
 * @param {{
 *   status?: string,
 *   kind?: string,
 *   q?: string,
 *   per_page?: number,
 *   page?: number,
 * }|null} [filters]
 */
export function useGoals(filters = null) {
    const [data, setData] = useState([]);
    const [meta, setMeta] = useState(null);
    const [status, setStatus] = useState('idle');
    const [error, setError] = useState(null);

    const paramsKey = JSON.stringify(filters ?? {});

    const refetch = useCallback(async () => {
        setStatus('loading');
        setError(null);

        try {
            const result = await listGoals(filters ?? {});
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
                const result = await listGoals(filters ?? {}, { signal: controller.signal });

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

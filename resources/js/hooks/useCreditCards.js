import { useCallback, useEffect, useState } from 'react';
import { listCreditCards } from '../api/creditCards';
import { getErrorMessage } from '../lib/errors';

/**
 * useCreditCards — lista paginada (PLAN_CARTOES_EMPRESTIMOS §6.2).
 *
 * @param {{
 *   q?: string,
 *   is_active?: boolean|0|1|string,
 *   page?: number,
 *   per_page?: number,
 *   enabled?: boolean,
 * }|null} [filters]
 */
export function useCreditCards(filters = null) {
    const [data, setData] = useState([]);
    const [meta, setMeta] = useState(null);
    const [status, setStatus] = useState('idle');
    const [error, setError] = useState(null);

    const enabled = filters?.enabled !== false;
    const paramsKey = JSON.stringify({
        ...(filters ?? {}),
        enabled,
    });

    const refetch = useCallback(async () => {
        if (!enabled) {
            return;
        }

        setStatus('loading');
        setError(null);

        try {
            const { enabled: _enabled, ...params } = filters ?? {};
            const result = await listCreditCards(params);
            setData(result.data ?? []);
            setMeta(result.meta ?? null);
            setStatus('success');
        } catch (err) {
            setError(getErrorMessage(err));
            setStatus('error');
        }
    }, [enabled, filters, paramsKey]);

    useEffect(() => {
        if (!enabled) {
            setData([]);
            setMeta(null);
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
                const { enabled: _enabled, ...params } = filters ?? {};
                const result = await listCreditCards(params, {
                    signal: controller.signal,
                });

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

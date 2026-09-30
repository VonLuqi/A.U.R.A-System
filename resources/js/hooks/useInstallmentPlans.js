import { useCallback, useEffect, useState } from 'react';
import { listInstallmentPlans } from '../api/installmentPlans';
import { getErrorMessage } from '../lib/errors';

/**
 * @param {{
 *   q?: string,
 *   status?: string,
 *   debtor_id?: number,
 *   credit_card_id?: number,
 *   page?: number,
 *   per_page?: number,
 *   backfill?: boolean|0|1,
 *   enabled?: boolean,
 * }|null} [filters]
 */
export function useInstallmentPlans(filters = null) {
    const [data, setData] = useState([]);
    const [meta, setMeta] = useState(null);
    const [status, setStatus] = useState('idle');
    const [error, setError] = useState(null);

    const enabled = filters?.enabled !== false;
    const paramsKey = JSON.stringify({ ...(filters ?? {}), enabled });

    const refetch = useCallback(async () => {
        if (!enabled) {
            return;
        }

        setStatus('loading');
        setError(null);

        try {
            const { enabled: _enabled, ...params } = filters ?? {};
            const result = await listInstallmentPlans(params);
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
                const result = await listInstallmentPlans(params, {
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

    return { data, meta, status, error, refetch };
}

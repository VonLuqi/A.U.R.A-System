import { useCallback, useEffect, useState } from 'react';
import { fetchDashboardAnalytics } from '../api/analytics';
import { getErrorMessage } from '../lib/errors';
import { toAnalyticsParams } from '../lib/apiParams';

/**
 * useDashboardAnalytics — Etapa D §4.3.
 * Refetch quando from/to/type/category_id/q/group_by mudam (não page).
 */
export function useDashboardAnalytics(filters) {
    const [data, setData] = useState(null);
    const [status, setStatus] = useState('idle');
    const [error, setError] = useState(null);

    const paramsKey = JSON.stringify(toAnalyticsParams(filters ?? {}));

    const refetch = useCallback(async () => {
        const controller = new AbortController();
        setStatus('loading');
        setError(null);

        try {
            const result = await fetchDashboardAnalytics(toAnalyticsParams(filters ?? {}), {
                signal: controller.signal,
            });
            setData(result);
            setStatus('success');
        } catch (err) {
            if (err?.code === 'ERR_CANCELED' || err?.name === 'CanceledError') {
                return controller;
            }

            setError(getErrorMessage(err));
            setStatus('error');
        }

        return controller;
    }, [filters, paramsKey]);

    useEffect(() => {
        let ignore = false;
        const controller = new AbortController();

        (async () => {
            setStatus('loading');
            setError(null);

            try {
                const result = await fetchDashboardAnalytics(toAnalyticsParams(filters ?? {}), {
                    signal: controller.signal,
                });

                if (!ignore) {
                    setData(result);
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
        // eslint-disable-next-line react-hooks/exhaustive-deps -- paramsKey captura filtros de analytics
    }, [paramsKey]);

    return {
        data,
        status,
        error,
        refetch,
    };
}

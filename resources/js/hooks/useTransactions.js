import { useCallback, useEffect, useState } from 'react';
import { listTransactions } from '../api/transactions';
import { getErrorMessage } from '../lib/errors';
import { toTransactionsParams } from '../lib/apiParams';

/**
 * useTransactions — Etapa D §4.3.
 * Inclui page/sort; corre em paralelo com analytics.
 */
export function useTransactions(filters) {
    const [data, setData] = useState([]);
    const [meta, setMeta] = useState(null);
    const [status, setStatus] = useState('idle');
    const [error, setError] = useState(null);

    const paramsKey = JSON.stringify(toTransactionsParams(filters ?? {}));

    const refetch = useCallback(async () => {
        setStatus('loading');
        setError(null);

        try {
            const result = await listTransactions(toTransactionsParams(filters ?? {}));
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
                const result = await listTransactions(toTransactionsParams(filters ?? {}), {
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

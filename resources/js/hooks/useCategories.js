import { useEffect, useState } from 'react';
import { listCategories } from '../api/categories';
import { getErrorMessage } from '../lib/errors';

/**
 * useCategories — Etapa D §4.3 (fetch once + cache em memória).
 */
export function useCategories() {
    const [data, setData] = useState([]);
    const [status, setStatus] = useState('idle');
    const [error, setError] = useState(null);

    useEffect(() => {
        let ignore = false;
        const controller = new AbortController();

        (async () => {
            setStatus('loading');
            setError(null);

            try {
                const result = await listCategories({ signal: controller.signal });

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
    }, []);

    return {
        data,
        status,
        error,
    };
}

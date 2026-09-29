import { useCallback, useEffect, useState } from 'react';
import { createCategory, listCategories } from '../api/categories';
import { getErrorMessage } from '../lib/errors';

/**
 * useCategories — Etapa D §4.3 (fetch + cache) / create for forms.
 */
export function useCategories() {
    const [data, setData] = useState([]);
    const [status, setStatus] = useState('idle');
    const [error, setError] = useState(null);

    const refresh = useCallback(async ({ force = true } = {}) => {
        setStatus('loading');
        setError(null);

        try {
            const result = await listCategories({ force });
            setData(result);
            setStatus('success');

            return result;
        } catch (err) {
            if (err?.code === 'ERR_CANCELED' || err?.name === 'CanceledError') {
                return [];
            }

            setError(getErrorMessage(err));
            setStatus('error');
            throw err;
        }
    }, []);

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

    const create = useCallback(
        async ({ name, type = 'expense', color = null }) => {
            const category = await createCategory({
                name,
                type,
                ...(color ? { color } : {}),
            });
            await refresh({ force: true });

            return category;
        },
        [refresh],
    );

    return {
        data,
        status,
        error,
        refresh,
        create,
    };
}

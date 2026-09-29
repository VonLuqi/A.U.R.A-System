import { useCallback, useState } from 'react';
import { toast } from 'sonner';
import { createAlias, deleteAlias, updateAlias } from '../api/aliases';
import { getErrorMessage, getValidationErrors } from '../lib/errors';
import { TOAST_DURATION } from '../lib/toast';

/**
 * @param {{ onSuccess?: (result: object) => void|Promise<void> }} [options]
 */
export function useCreateAlias({ onSuccess } = {}) {
    const [status, setStatus] = useState('idle');

    const mutate = useCallback(
        async (payload) => {
            setStatus('loading');

            try {
                const result = await createAlias(payload);
                const updated = result.retroactive?.updated;

                if (typeof updated === 'number' && updated > 0) {
                    toast.success(
                        `Apelido criado. ${updated} lançamento${updated === 1 ? '' : 's'} atualizado${updated === 1 ? '' : 's'}.`,
                        { duration: TOAST_DURATION },
                    );
                } else {
                    toast.success('Apelido criado.', { duration: TOAST_DURATION });
                }

                await onSuccess?.(result);
                setStatus('success');
                return result;
            } catch (error) {
                setStatus('error');
                if (!getValidationErrors(error)) {
                    toast.error(getErrorMessage(error), { duration: TOAST_DURATION });
                }
                throw error;
            }
        },
        [onSuccess],
    );

    return { mutate, status, isLoading: status === 'loading' };
}

/**
 * @param {{ onSuccess?: (alias: object) => void|Promise<void> }} [options]
 */
export function useUpdateAlias({ onSuccess } = {}) {
    const [status, setStatus] = useState('idle');

    const mutate = useCallback(
        async (id, payload) => {
            setStatus('loading');

            try {
                const data = await updateAlias(id, payload);
                toast.success('Apelido atualizado.', { duration: TOAST_DURATION });
                await onSuccess?.(data);
                setStatus('success');
                return data;
            } catch (error) {
                setStatus('error');
                if (!getValidationErrors(error)) {
                    toast.error(getErrorMessage(error), { duration: TOAST_DURATION });
                }
                throw error;
            }
        },
        [onSuccess],
    );

    return { mutate, status, isLoading: status === 'loading' };
}

/**
 * @param {{ onSuccess?: () => void|Promise<void> }} [options]
 */
export function useDeleteAlias({ onSuccess } = {}) {
    const [status, setStatus] = useState('idle');

    const mutate = useCallback(
        async (id) => {
            setStatus('loading');

            try {
                const data = await deleteAlias(id);
                toast.success('Apelido removido.', { duration: TOAST_DURATION });
                await onSuccess?.();
                setStatus('success');
                return data;
            } catch (error) {
                setStatus('error');
                toast.error(getErrorMessage(error), { duration: TOAST_DURATION });
                throw error;
            }
        },
        [onSuccess],
    );

    return { mutate, status, isLoading: status === 'loading' };
}

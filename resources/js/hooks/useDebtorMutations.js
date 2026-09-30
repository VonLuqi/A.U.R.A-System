import { useCallback, useState } from 'react';
import { toast } from 'sonner';
import { createDebtor, deleteDebtor, updateDebtor } from '../api/debtors';
import { getErrorMessage, getValidationErrors } from '../lib/errors';
import { TOAST_DURATION } from '../lib/toast';

/**
 * @param {{ onSuccess?: (debtor: object) => void|Promise<void> }} [options]
 */
export function useCreateDebtor({ onSuccess } = {}) {
    const [status, setStatus] = useState('idle');

    const mutate = useCallback(
        async (payload) => {
            setStatus('loading');

            try {
                const data = await createDebtor(payload);
                toast.success('Pessoa cadastrada.', { duration: TOAST_DURATION });
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
 * @param {{ onSuccess?: (debtor: object) => void|Promise<void> }} [options]
 */
export function useUpdateDebtor({ onSuccess } = {}) {
    const [status, setStatus] = useState('idle');

    const mutate = useCallback(
        async (id, payload) => {
            setStatus('loading');

            try {
                const data = await updateDebtor(id, payload);
                toast.success('Pessoa atualizada.', { duration: TOAST_DURATION });
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
export function useDeleteDebtor({ onSuccess } = {}) {
    const [status, setStatus] = useState('idle');

    const mutate = useCallback(
        async (id) => {
            setStatus('loading');

            try {
                const data = await deleteDebtor(id);
                toast.success('Pessoa removida.', { duration: TOAST_DURATION });
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

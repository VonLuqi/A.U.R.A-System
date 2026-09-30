import { useCallback, useState } from 'react';
import { toast } from 'sonner';
import {
    createTransaction,
    deleteTransaction,
    rememberAlias,
    updateTransaction,
} from '../api/transactions';
import { getErrorMessage, getValidationErrors } from '../lib/errors';
import { TOAST_DURATION } from '../lib/toast';

/**
 * @param {{ onSuccess?: (tx: object) => void|Promise<void> }} [options]
 */
export function useCreateTransaction({ onSuccess } = {}) {
    const [status, setStatus] = useState('idle');

    const mutate = useCallback(
        async (payload) => {
            setStatus('loading');

            try {
                const data = await createTransaction(payload);
                toast.success('Lançamento criado.', { duration: TOAST_DURATION });
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

    return {
        mutate,
        status,
        isLoading: status === 'loading',
    };
}

/**
 * @param {{ onSuccess?: (tx: object) => void|Promise<void> }} [options]
 */
export function useUpdateTransaction({ onSuccess } = {}) {
    const [status, setStatus] = useState('idle');

    const mutate = useCallback(
        async (id, payload) => {
            setStatus('loading');

            try {
                const result = await updateTransaction(id, payload);
                const updated = result.retroactive?.updated;

                if (typeof updated === 'number' && updated > 0) {
                    toast.success(
                        `Lançamento atualizado. Categoria aplicada em ${updated} outro${updated === 1 ? '' : 's'}.`,
                        { duration: TOAST_DURATION },
                    );
                } else {
                    toast.success('Lançamento atualizado.', { duration: TOAST_DURATION });
                }

                await onSuccess?.(result.data);
                setStatus('success');
                return result.data;
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

    return {
        mutate,
        status,
        isLoading: status === 'loading',
    };
}

/**
 * @param {{ onSuccess?: () => void|Promise<void> }} [options]
 */
export function useDeleteTransaction({ onSuccess } = {}) {
    const [status, setStatus] = useState('idle');

    const mutate = useCallback(
        async (id) => {
            setStatus('loading');

            try {
                const data = await deleteTransaction(id);
                toast.success('Lançamento excluído.', { duration: TOAST_DURATION });
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

    return {
        mutate,
        status,
        isLoading: status === 'loading',
    };
}

/**
 * @param {{ onSuccess?: (result: object) => void|Promise<void> }} [options]
 */
export function useRememberAlias({ onSuccess } = {}) {
    const [status, setStatus] = useState('idle');

    const mutate = useCallback(
        async (transactionId, payload) => {
            setStatus('loading');

            try {
                const data = await rememberAlias(transactionId, payload);
                toast.success('Apelido lembrado.', { duration: TOAST_DURATION });
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

    return {
        mutate,
        status,
        isLoading: status === 'loading',
    };
}

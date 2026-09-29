import { useCallback, useState } from 'react';
import { toast } from 'sonner';
import { createUser, deactivateUser, updateUser } from '../api/users';
import { getErrorMessage, getValidationErrors } from '../lib/errors';
import { TOAST_DURATION } from '../lib/toast';

/**
 * @param {{ onSuccess?: (user: object) => void|Promise<void> }} [options]
 */
export function useCreateUser({ onSuccess } = {}) {
    const [status, setStatus] = useState('idle');

    const mutate = useCallback(
        async (payload) => {
            setStatus('loading');

            try {
                const data = await createUser(payload);
                toast.success('Usuário criado.', { duration: TOAST_DURATION });
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
 * @param {{ onSuccess?: (user: object) => void|Promise<void> }} [options]
 */
export function useUpdateUser({ onSuccess } = {}) {
    const [status, setStatus] = useState('idle');

    const mutate = useCallback(
        async (id, payload) => {
            setStatus('loading');

            try {
                const data = await updateUser(id, payload);
                toast.success('Usuário atualizado.', { duration: TOAST_DURATION });
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
 * @param {{ onSuccess?: (result: object) => void|Promise<void> }} [options]
 */
export function useDeactivateUser({ onSuccess } = {}) {
    const [status, setStatus] = useState('idle');

    const mutate = useCallback(
        async (id) => {
            setStatus('loading');

            try {
                const data = await deactivateUser(id);
                toast.success(data.message || 'Usuário desativado.', {
                    duration: TOAST_DURATION,
                });
                await onSuccess?.(data);
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

/**
 * Reset cotas via PATCH `reset_usage`.
 * @param {{ onSuccess?: (user: object) => void|Promise<void> }} [options]
 */
export function useResetUserUsage({ onSuccess } = {}) {
    const [status, setStatus] = useState('idle');

    const mutate = useCallback(
        async (id) => {
            setStatus('loading');

            try {
                const data = await updateUser(id, { reset_usage: true });
                toast.success('Cotas reiniciadas.', { duration: TOAST_DURATION });
                await onSuccess?.(data);
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

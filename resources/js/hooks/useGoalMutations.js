import { useCallback, useState } from 'react';
import { toast } from 'sonner';
import { createGoal, deleteGoal, updateGoal } from '../api/goals';
import { getErrorMessage, getValidationErrors } from '../lib/errors';
import { TOAST_DURATION } from '../lib/toast';

/**
 * @param {{ onSuccess?: (goal: object) => void|Promise<void> }} [options]
 */
export function useCreateGoal({ onSuccess } = {}) {
    const [status, setStatus] = useState('idle');

    const mutate = useCallback(
        async (payload) => {
            setStatus('loading');

            try {
                const data = await createGoal(payload);
                toast.success('Meta criada.', { duration: TOAST_DURATION });
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
 * @param {{ onSuccess?: (goal: object) => void|Promise<void> }} [options]
 */
export function useUpdateGoal({ onSuccess } = {}) {
    const [status, setStatus] = useState('idle');

    const mutate = useCallback(
        async (id, payload) => {
            setStatus('loading');

            try {
                const data = await updateGoal(id, payload);
                toast.success('Meta atualizada.', { duration: TOAST_DURATION });
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
export function useDeleteGoal({ onSuccess } = {}) {
    const [status, setStatus] = useState('idle');

    const mutate = useCallback(
        async (id) => {
            setStatus('loading');

            try {
                const data = await deleteGoal(id);
                toast.success('Meta removida.', { duration: TOAST_DURATION });
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

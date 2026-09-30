import { useCallback, useState } from 'react';
import { toast } from 'sonner';
import {
    cancelLoan,
    createLoan,
    deleteLoan,
    markLoanPaid,
    updateLoan,
} from '../api/loans';
import { getErrorMessage, getValidationErrors } from '../lib/errors';
import { TOAST_DURATION } from '../lib/toast';

/**
 * @param {{ onSuccess?: (loan: object) => void|Promise<void> }} [options]
 */
export function useCreateLoan({ onSuccess } = {}) {
    const [status, setStatus] = useState('idle');

    const mutate = useCallback(
        async (payload) => {
            setStatus('loading');

            try {
                const data = await createLoan(payload);
                toast.success(
                    payload?.create_expense
                        ? 'Cobrança criada com saída vinculada.'
                        : 'Cobrança criada.',
                    { duration: TOAST_DURATION },
                );
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
 * @param {{ onSuccess?: (loan: object) => void|Promise<void> }} [options]
 */
export function useUpdateLoan({ onSuccess } = {}) {
    const [status, setStatus] = useState('idle');

    const mutate = useCallback(
        async (id, payload) => {
            setStatus('loading');

            try {
                const data = await updateLoan(id, payload);
                toast.success('Cobrança atualizada.', { duration: TOAST_DURATION });
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
export function useDeleteLoan({ onSuccess } = {}) {
    const [status, setStatus] = useState('idle');

    const mutate = useCallback(
        async (id) => {
            setStatus('loading');

            try {
                const data = await deleteLoan(id);
                toast.success('Cobrança removida.', { duration: TOAST_DURATION });
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

/**
 * @param {{ onSuccess?: (loan: object) => void|Promise<void> }} [options]
 */
export function useMarkLoanPaid({ onSuccess } = {}) {
    const [status, setStatus] = useState('idle');

    const mutate = useCallback(
        async (id, payload = {}) => {
            setStatus('loading');

            try {
                const data = await markLoanPaid(id, payload);
                const debtor = typeof data?.debtor_name === 'string' && data.debtor_name.trim()
                    ? data.debtor_name.trim()
                    : null;

                if (data?.status === 'paid' && debtor) {
                    toast.success(`Cobrança de ${debtor} marcada como paga`, {
                        duration: TOAST_DURATION,
                    });
                } else if (debtor) {
                    toast.success(`Pagamento parcial registrado para ${debtor}.`, {
                        duration: TOAST_DURATION,
                    });
                } else {
                    toast.success('Pagamento registrado.', { duration: TOAST_DURATION });
                }

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
 * @param {{ onSuccess?: (loan: object) => void|Promise<void> }} [options]
 */
export function useCancelLoan({ onSuccess } = {}) {
    const [status, setStatus] = useState('idle');

    const mutate = useCallback(
        async (id) => {
            setStatus('loading');

            try {
                const data = await cancelLoan(id);
                toast.success('Cobrança cancelada.', { duration: TOAST_DURATION });
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

import { useCallback, useState } from 'react';
import {
    cancelInstallmentPlan,
    createInstallmentPlan,
    markInstallmentItemOpen,
    markInstallmentItemPaid,
    updateInstallmentPlan,
} from '../api/installmentPlans';
import { getErrorMessage } from '../lib/errors';

function useMutation(fn, { onSuccess } = {}) {
    const [status, setStatus] = useState('idle');
    const [error, setError] = useState(null);

    const mutate = useCallback(
        async (...args) => {
            setStatus('loading');
            setError(null);
            try {
                const result = await fn(...args);
                setStatus('success');
                if (onSuccess) {
                    await onSuccess(result);
                }
                return result;
            } catch (err) {
                setError(getErrorMessage(err));
                setStatus('error');
                throw err;
            }
        },
        [fn, onSuccess],
    );

    return { mutate, status, error, isLoading: status === 'loading' };
}

/** @param {{ onSuccess?: (plan: object) => void|Promise<void> }} [opts] */
export function useCreateInstallmentPlan(opts = {}) {
    return useMutation(createInstallmentPlan, opts);
}

/** @param {{ onSuccess?: (plan: object) => void|Promise<void> }} [opts] */
export function useUpdateInstallmentPlan(opts = {}) {
    return useMutation(
        (id, payload) => updateInstallmentPlan(id, payload),
        opts,
    );
}

/** @param {{ onSuccess?: (plan: object) => void|Promise<void> }} [opts] */
export function useCancelInstallmentPlan(opts = {}) {
    return useMutation(cancelInstallmentPlan, opts);
}

/** @param {{ onSuccess?: (plan: object) => void|Promise<void> }} [opts] */
export function useMarkInstallmentItemPaid(opts = {}) {
    return useMutation(
        (planId, number) => markInstallmentItemPaid(planId, number),
        opts,
    );
}

/** @param {{ onSuccess?: (plan: object) => void|Promise<void> }} [opts] */
export function useMarkInstallmentItemOpen(opts = {}) {
    return useMutation(
        (planId, number) => markInstallmentItemOpen(planId, number),
        opts,
    );
}

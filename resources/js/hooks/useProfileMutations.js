import { useCallback, useState } from 'react';
import { toast } from 'sonner';
import { deleteAvatar, updateProfile, uploadAvatar } from '../api/profile';
import { useAuth } from './useAuth';
import { getErrorMessage, getValidationErrors } from '../lib/errors';
import { TOAST_DURATION } from '../lib/toast';

/**
 * Profile mutations — Etapa I §3.6.
 * Padrão: toast + `applyUser` (sem React Query; AuthContext é a fonte de verdade).
 *
 * | Hook | API | Toast sucesso |
 * | --- | --- | --- |
 * | `useUpdateProfile` | `PATCH /api/profile` | Conta atualizada. |
 * | `useUploadAvatar` | `POST /api/profile/avatar` | Foto de perfil atualizada. |
 * | `useDeleteAvatar` | `DELETE /api/profile/avatar` | Foto de perfil removida. |
 */

/**
 * @param {{ onSuccess?: (user: import('../lib/auth').AuthUser) => void|Promise<void> }} [options]
 */
export function useUpdateProfile({ onSuccess } = {}) {
    const { applyUser } = useAuth();
    const [status, setStatus] = useState('idle');

    const mutate = useCallback(
        async (payload) => {
            setStatus('loading');

            try {
                const user = await updateProfile(payload);
                applyUser(user);
                toast.success('Conta atualizada.', { duration: TOAST_DURATION });
                await onSuccess?.(user);
                setStatus('success');
                return user;
            } catch (error) {
                setStatus('error');
                if (!getValidationErrors(error)) {
                    toast.error(getErrorMessage(error), { duration: TOAST_DURATION });
                }
                throw error;
            }
        },
        [applyUser, onSuccess],
    );

    return { mutate, status, isLoading: status === 'loading' };
}

/**
 * @param {{ onSuccess?: (user: import('../lib/auth').AuthUser) => void|Promise<void> }} [options]
 */
export function useUploadAvatar({ onSuccess } = {}) {
    const { applyUser } = useAuth();
    const [status, setStatus] = useState('idle');

    const mutate = useCallback(
        async (file) => {
            setStatus('loading');

            try {
                const user = await uploadAvatar(file);
                applyUser(user);
                toast.success('Foto de perfil atualizada.', { duration: TOAST_DURATION });
                await onSuccess?.(user);
                setStatus('success');
                return user;
            } catch (error) {
                setStatus('error');
                if (!getValidationErrors(error)) {
                    toast.error(getErrorMessage(error), { duration: TOAST_DURATION });
                }
                throw error;
            }
        },
        [applyUser, onSuccess],
    );

    return { mutate, status, isLoading: status === 'loading' };
}

/**
 * @param {{ onSuccess?: (user: import('../lib/auth').AuthUser) => void|Promise<void> }} [options]
 */
export function useDeleteAvatar({ onSuccess } = {}) {
    const { applyUser } = useAuth();
    const [status, setStatus] = useState('idle');

    const mutate = useCallback(
        async () => {
            setStatus('loading');

            try {
                const user = await deleteAvatar();
                applyUser(user);
                toast.success('Foto de perfil removida.', { duration: TOAST_DURATION });
                await onSuccess?.(user);
                setStatus('success');
                return user;
            } catch (error) {
                setStatus('error');
                toast.error(getErrorMessage(error), { duration: TOAST_DURATION });
                throw error;
            }
        },
        [applyUser, onSuccess],
    );

    return { mutate, status, isLoading: status === 'loading' };
}

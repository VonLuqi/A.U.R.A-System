import { Toaster as SonnerToaster } from 'sonner';
import { useMediaQuery } from '../../hooks/useMediaQuery';
import { MQ_MOBILE } from '../../lib/breakpoints';
import { TOAST_DURATION } from '../../lib/toast';

export { TOAST_DURATION, TOAST_DURATION_UPLOAD } from '../../lib/toast';

/**
 * Toaster — Etapa D §5.3.3 / §6.1.
 * Posição: top-right desktop/tablet · top-center mobile (<640px).
 */
export default function Toaster() {
    const isMobile = useMediaQuery(MQ_MOBILE);

    return (
        <SonnerToaster
            theme="dark"
            position={isMobile ? 'top-center' : 'top-right'}
            duration={TOAST_DURATION}
            closeButton
            toastOptions={{
                duration: TOAST_DURATION,
                classNames: {
                    toast: 'aura-toast',
                    title: 'aura-toast__title',
                    description: 'aura-toast__description',
                    success: 'aura-toast--success',
                    error: 'aura-toast--error',
                    closeButton: 'aura-toast__close',
                },
            }}
        />
    );
}

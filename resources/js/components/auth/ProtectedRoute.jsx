import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import AuraLoader from '../ui/AuraLoader';

/**
 * ProtectedRoute — auth guard (Etapa D §1.2.2 / §5.6 · Etapa I §5.3).
 * Upload e Dashboard só após sessão autenticada (cookie); API exige auth no backend.
 */
export default function ProtectedRoute() {
    const { user, status } = useAuth();
    const location = useLocation();

    if (status === 'loading' || status === 'idle') {
        return (
            <div className="flex min-h-dvh flex-col items-center justify-center bg-canvas">
                <AuraLoader size="lg" label="Carregando sessão" />
            </div>
        );
    }

    if (!user) {
        return <Navigate to="/login" replace state={{ from: location }} />;
    }

    return <Outlet />;
}

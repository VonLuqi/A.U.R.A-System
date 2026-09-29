import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import BrandMark from '../ui/BrandMark';
import Spinner from '../ui/Spinner';

/**
 * ProtectedRoute — auth guard (Etapa D §1.2.2 / §5.6).
 * Upload e Dashboard só após sessão autenticada (cookie); API exige auth no backend.
 */
export default function ProtectedRoute() {
    const { user, status } = useAuth();
    const location = useLocation();

    if (status === 'loading' || status === 'idle') {
        return (
            <div className="flex min-h-dvh flex-col items-center justify-center gap-6 bg-canvas">
                <BrandMark size="lg" />
                <Spinner size="lg" />
                <span className="sr-only">Carregando sessão</span>
            </div>
        );
    }

    if (!user) {
        return <Navigate to="/login" replace state={{ from: location }} />;
    }

    return <Outlet />;
}

import { Navigate } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';

/**
 * RootRedirect — `/` (Etapa D §0.3 / §1.2).
 * auth → /dashboard; guest → /login.
 */
export default function RootRedirect() {
    const { user } = useAuth();

    return <Navigate to={user ? '/dashboard' : '/login'} replace />;
}

import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';

/**
 * GuestRoute — guest only (Etapa D §1.2.3).
 * Se autenticado → /dashboard; senão → Outlet (LoginPage, sem AppShell).
 */
export default function GuestRoute() {
    const { user } = useAuth();

    if (user) {
        return <Navigate to="/dashboard" replace />;
    }

    return <Outlet />;
}

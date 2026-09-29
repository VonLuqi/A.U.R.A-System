import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { can, isRole } from '../../lib/auth';
import BrandMark from '../ui/BrandMark';
import Spinner from '../ui/Spinner';

/**
 * AbilityRoute — exige ability e/ou papel (PLAN_EXPANSAO §8.1).
 *
 * @param {{
 *   ability?: string,
 *   roles?: string[],
 *   fallback?: string,
 * }} props
 */
export default function AbilityRoute({
    ability,
    roles,
    fallback = '/dashboard',
}) {
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

    if (roles?.length && !isRole(user, roles)) {
        return <Navigate to={fallback} replace />;
    }

    if (ability && !can(user, ability)) {
        return <Navigate to={fallback} replace />;
    }

    return <Outlet />;
}

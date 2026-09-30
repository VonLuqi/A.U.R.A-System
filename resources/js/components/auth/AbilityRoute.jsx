import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { can, featureEnabled, isRole } from '../../lib/auth';
import AuraLoader from '../ui/AuraLoader';

/**
 * AbilityRoute — exige ability, feature flag e/ou papel
 * (PLAN_EXPANSAO §8.1 / PLAN_CARTOES_EMPRESTIMOS §6.1 · Etapa I §5.3).
 *
 * @param {{
 *   ability?: string,
 *   feature?: string,
 *   roles?: string[],
 *   fallback?: string,
 * }} props
 */
export default function AbilityRoute({
    ability,
    feature,
    roles,
    fallback = '/dashboard',
}) {
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

    if (roles?.length && !isRole(user, roles)) {
        return <Navigate to={fallback} replace />;
    }

    if (feature && !featureEnabled(user, feature)) {
        return <Navigate to={fallback} replace />;
    }

    if (ability && !can(user, ability)) {
        return <Navigate to={fallback} replace />;
    }

    return <Outlet />;
}

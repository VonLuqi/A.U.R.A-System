import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { toast } from 'sonner';
import * as authApi from '../api/auth';
import { setUnauthorizedHandler } from '../api/client';
import BrandMark from '../components/ui/BrandMark';
import Spinner from '../components/ui/Spinner';

/**
 * AuthContext (Etapa D §1.2.1 / PLAN_EXPANSAO §8.1) — sessão SPA.
 * Contrato: `{ user, status, login, logout, refreshUser }`
 * `user` = AuthUser (`role`, `limits`, `usage`, `abilities`) — ver `lib/auth.js`.
 * status: `idle` | `loading` | `ready` (mount usa `loading` → `ready`).
 *
 * Segurança client (§5.6):
 * - Sessão só em cookie HttpOnly (sem localStorage/sessionStorage).
 * - Nunca logar senha ou payload de login no console.
 */

const AuthContext = createContext(null);

function AuthSplash() {
    return (
        <div className="flex min-h-dvh flex-col items-center justify-center gap-6 bg-canvas">
            <BrandMark size="lg" />
            <Spinner size="lg" />
            <span className="sr-only">Carregando sessão</span>
        </div>
    );
}

export function AuthProvider({ children }) {
    const navigate = useNavigate();
    const [user, setUser] = useState(null);
    /** @type {[ 'idle' | 'loading' | 'ready', function ]} */
    const [status, setStatus] = useState('loading');

    useEffect(() => {
        let cancelled = false;

        (async () => {
            setStatus('loading');

            try {
                await authApi.ensureCsrf();
                const current = await authApi.fetchUser();

                if (!cancelled) {
                    setUser(current);
                    setStatus('ready');
                }
            } catch {
                if (!cancelled) {
                    setUser(null);
                    setStatus('ready');
                }
            }
        })();

        return () => {
            cancelled = true;
        };
    }, []);

    const clearSession = useCallback(() => {
        setUser(null);
        navigate('/login', { replace: true });
    }, [navigate]);

    useEffect(() => {
        setUnauthorizedHandler(() => {
            clearSession();
        });

        return () => setUnauthorizedHandler(null);
    }, [clearSession]);

    const login = useCallback(async ({ email, password }) => {
        const nextUser = await authApi.login({ email, password });
        setUser(nextUser);

        return nextUser;
    }, []);

    const logout = useCallback(async () => {
        try {
            await authApi.logout();
            toast.success('Sessão encerrada.');
        } catch {
            // Fail-open no client (§1.4): limpa estado mesmo se a API falhar.
        } finally {
            clearSession();
        }
    }, [clearSession]);

    const refreshUser = useCallback(async () => {
        const current = await authApi.fetchUser();
        setUser(current);

        return current;
    }, []);

    const value = useMemo(
        () => ({
            user,
            status,
            login,
            logout,
            refreshUser,
        }),
        [user, status, login, logout, refreshUser],
    );

    return (
        <AuthContext.Provider value={value}>
            {status !== 'ready' ? <AuthSplash /> : children}
        </AuthContext.Provider>
    );
}

/**
 * Hook canônico de autenticação (§1.2.1).
 * @throws {Error} se usado fora de `AuthProvider`
 */
export function useAuth() {
    const ctx = useContext(AuthContext);

    if (!ctx) {
        throw new Error('useAuth must be used within AuthProvider');
    }

    return ctx;
}

/** @deprecated Preferir `useAuth` */
export const useAuthContext = useAuth;

export default AuthContext;

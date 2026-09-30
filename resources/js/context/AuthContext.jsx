import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { toast } from 'sonner';
import * as authApi from '../api/auth';
import { setUnauthorizedHandler } from '../api/client';
import AuraLoader from '../components/ui/AuraLoader';

/**
 * AuthContext (Etapa D §1.2.1 / PLAN_EXPANSAO §8.1 · Etapa I §3.3 / §5.3) — sessão SPA.
 * Contrato: `{ user, status, login, logout, refreshUser, applyUser }`
 * `user` = AuthUser (`role`, `limits`, `usage`, `abilities`, `avatar_url`) — ver `lib/auth.js`.
 * status: `idle` | `loading` | `ready` (mount usa `loading` → `ready`).
 *
 * Pós-mutação de perfil (§3.3): API → `applyUser(user)` imediato → toast (no mutation hook).
 * `refreshUser()` só se a resposta não trouxer o resource completo.
 *
 * Segurança client (§5.6):
 * - Sessão só em cookie HttpOnly (sem localStorage/sessionStorage).
 * - Nunca logar senha ou payload de login no console.
 */

const AuthContext = createContext(null);

/** Tempo mínimo do splash para o pulse/glow ser perceptível (~½ ciclo). */
const SPLASH_MIN_MS = 1100;

function AuthSplash() {
    return (
        <div className="flex min-h-dvh flex-col items-center justify-center bg-canvas">
            <AuraLoader size="lg" label="Carregando sessão" />
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
        const startedAt = performance.now();

        (async () => {
            setStatus('loading');

            let nextUser = null;

            try {
                await authApi.ensureCsrf();
                nextUser = await authApi.fetchUser();
            } catch {
                nextUser = null;
            }

            const remaining = SPLASH_MIN_MS - (performance.now() - startedAt);

            if (remaining > 0) {
                await new Promise((resolve) => {
                    window.setTimeout(resolve, remaining);
                });
            }

            if (!cancelled) {
                setUser(nextUser);
                setStatus('ready');
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

    /**
     * Atualiza o user no context sem refetch (Etapa I §3.3).
     * Usar após PATCH/POST/DELETE de perfil com o `user` retornado pela API.
     *
     * @param {import('../lib/auth').AuthUser|null} nextUser
     */
    const applyUser = useCallback((nextUser) => {
        setUser(nextUser);
    }, []);

    const value = useMemo(
        () => ({
            user,
            status,
            login,
            logout,
            refreshUser,
            applyUser,
        }),
        [user, status, login, logout, refreshUser, applyUser],
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

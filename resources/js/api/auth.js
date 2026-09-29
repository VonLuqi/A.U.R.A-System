/**
 * Auth API — session cookie + CSRF (Etapa D §5.1 / PLAN_EXPANSAO §8.1).
 * Sem Sanctum. Sem registro público.
 *
 * | Método | Path | Resposta |
 * | --- | --- | --- |
 * | GET | `/api/csrf-cookie` | 204 (seta XSRF-TOKEN) |
 * | POST | `/api/login` | `{ user: AuthUser }` |
 * | POST | `/api/logout` | 204 |
 * | GET | `/api/user` | `{ user: AuthUser }` · 401 → guest |
 *
 * @typedef {import('../lib/auth').AuthUser} AuthUser
 */
import api from './client';

/** Mensagem única de falha de login — não revelar se o e-mail existe. */
export const INVALID_CREDENTIALS_MESSAGE = 'Credenciais inválidas.';

export class AuthError extends Error {
    /**
     * @param {string} message
     * @param {{ status?: number, code?: string }} [meta]
     */
    constructor(message, { status, code } = {}) {
        super(message);
        this.name = 'AuthError';
        this.status = status;
        this.code = code;
    }
}

/**
 * @param {unknown} error
 * @returns {error is AuthError}
 */
export function isInvalidCredentialsError(error) {
    return error instanceof AuthError && error.code === 'invalid_credentials';
}

/**
 * Bootstrap CSRF: seta cookie XSRF-TOKEN (+ sessão).
 * @returns {Promise<void>}
 */
export async function ensureCsrf() {
    await api.get('/api/csrf-cookie');
}

/**
 * @param {{ email: string, password: string }} credentials
 * @returns {Promise<AuthUser>}
 */
export async function login({ email, password }) {
    await ensureCsrf();

    try {
        const { data } = await api.post('/api/login', { email, password });

        return data.user;
    } catch (error) {
        if (error.response?.status === 401) {
            throw new AuthError(INVALID_CREDENTIALS_MESSAGE, {
                status: 401,
                code: 'invalid_credentials',
            });
        }

        throw error;
    }
}

/**
 * Invalida a sessão no servidor (204).
 * Limpeza de estado local fica a cargo do AuthContext (§1.2.1).
 * @returns {Promise<void>}
 */
export async function logout() {
    await api.post('/api/logout');
}

/**
 * Usuário autenticado ou `null` se guest (401).
 * @returns {Promise<AuthUser|null>}
 */
export async function fetchUser() {
    try {
        const { data } = await api.get('/api/user');

        return data.user ?? null;
    } catch (error) {
        if (error.response?.status === 401) {
            return null;
        }

        throw error;
    }
}

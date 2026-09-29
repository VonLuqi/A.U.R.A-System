import axios from 'axios';
import { toast } from 'sonner';
import '../bootstrap';
import {
    getErrorMessage,
    isCanceledError,
    NETWORK_ERROR_MESSAGE,
    SESSION_EXPIRED_MESSAGE,
} from '../lib/errors';

/**
 * Dedicated Axios instance for Aura SPA (Etapa D §5.1 / §5.2 / §1.1.1).
 * Session cookie + CSRF (XSRF-TOKEN → X-XSRF-TOKEN). No Sanctum.
 * Domínios HTTP usam `api/*.js` — pages/hooks não chamam axios direto.
 *
 * Interceptor (§5.2): 401 logout · 419 CSRF retry · 429/500 toast · network toast.
 */

const api = axios.create({
    baseURL: '/',
    withCredentials: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    xsrfCookieName: 'XSRF-TOKEN',
    xsrfHeaderName: 'X-XSRF-TOKEN',
});

const CSRF_RETRY_FLAG = '__auraCsrfRetry';

/** @type {(() => void) | null} */
let onUnauthorized = null;

/**
 * AuthContext registra o handler: limpar user + navigate('/login').
 * @param {(() => void) | null} handler
 */
export function setUnauthorizedHandler(handler) {
    onUnauthorized = handler;
}

/**
 * @param {import('axios').InternalAxiosRequestConfig | undefined} config
 */
function shouldSkipUnauthorizedRedirect(config) {
    const url = config?.url ?? '';
    const method = (config?.method ?? 'get').toLowerCase();

    if (method === 'post' && url.includes('/api/login')) {
        return true;
    }

    if (method === 'get' && url.includes('/api/user')) {
        return true;
    }

    return false;
}

/**
 * @param {import('axios').AxiosError} error
 * @returns {string}
 */
function retryAfterMessage(error) {
    const base = getErrorMessage(error);
    const raw = error.response?.headers?.['retry-after'];
    const seconds = raw !== undefined && raw !== null ? Number(raw) : NaN;

    if (!Number.isFinite(seconds) || seconds <= 0) {
        return base;
    }

    return `${base} Aguarde ${Math.ceil(seconds)} s.`;
}

/**
 * Leave Content-Type unset for FormData so the browser sets multipart boundary.
 * @param {import('axios').InternalAxiosRequestConfig} config
 */
function stripFormDataContentType(config) {
    if (!(config.data instanceof FormData) || !config.headers) {
        return config;
    }

    const headers = config.headers;

    if (typeof headers.delete === 'function') {
        headers.delete('Content-Type');
        headers.delete('content-type');
    } else {
        delete headers['Content-Type'];
        delete headers['content-type'];
    }

    return config;
}

api.interceptors.request.use((config) => stripFormDataContentType(config));

api.interceptors.response.use(
    (response) => response,
    async (error) => {
        if (isCanceledError(error)) {
            return Promise.reject(error);
        }

        const status = error.response?.status;
        const config = error.config;

        // Network / sem response
        if (!error.response) {
            toast.error(NETWORK_ERROR_MESSAGE);

            return Promise.reject(error);
        }

        if (status === 401 && !shouldSkipUnauthorizedRedirect(config)) {
            onUnauthorized?.();

            return Promise.reject(error);
        }

        if (status === 419 && config) {
            if (config[CSRF_RETRY_FLAG]) {
                toast.error(SESSION_EXPIRED_MESSAGE);
                onUnauthorized?.();

                return Promise.reject(error);
            }

            config[CSRF_RETRY_FLAG] = true;

            try {
                // Dynamic import evita ciclo client ↔ auth.
                const { ensureCsrf } = await import('./auth');
                await ensureCsrf();

                return api.request(config);
            } catch (csrfError) {
                toast.error(SESSION_EXPIRED_MESSAGE);
                onUnauthorized?.();

                return Promise.reject(csrfError);
            }
        }

        if (status === 429) {
            toast.error(retryAfterMessage(error));

            return Promise.reject(error);
        }

        if (status === 500 || status === 503) {
            toast.error(getErrorMessage(error));

            return Promise.reject(error);
        }

        return Promise.reject(error);
    },
);

export default api;

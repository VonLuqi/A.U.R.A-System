import axios from 'axios';
import { toast } from 'sonner';
import '../bootstrap';
import {
    getErrorMessage,
    getRetryAfterSeconds,
    isCanceledError,
    NETWORK_ERROR_MESSAGE,
    RATE_LIMIT_MESSAGE,
    RATE_LIMIT_RETRY_CAP_SECONDS,
    SESSION_EXPIRED_MESSAGE,
} from '../lib/errors';
import { TOAST_DURATION } from '../lib/toast';

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
const RATE_LIMIT_TOAST_ID = 'aura-rate-limit';

/** @type {number} epoch ms — suppress stacked 429 toasts until then */
let rateLimitQuietUntil = 0;

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
    const base = getErrorMessage(error) || RATE_LIMIT_MESSAGE;
    const seconds = getRetryAfterSeconds(error);

    if (seconds == null) {
        return base;
    }

    if (seconds > RATE_LIMIT_RETRY_CAP_SECONDS) {
        return `${base} Aguarde alguns minutos.`;
    }

    return `${base} Aguarde ${seconds} s.`;
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

/**
 * @param {import('axios').AxiosError} error
 */
function toastRateLimitOnce(error) {
    const now = Date.now();
    const retrySeconds = getRetryAfterSeconds(error);
    const waitSeconds = Math.min(
        retrySeconds != null && retrySeconds > 0 ? retrySeconds : 60,
        RATE_LIMIT_RETRY_CAP_SECONDS,
    );

    if (now < rateLimitQuietUntil) {
        return;
    }

    rateLimitQuietUntil = now + waitSeconds * 1000;

    toast.error(retryAfterMessage(error), {
        id: RATE_LIMIT_TOAST_ID,
        duration: Math.min(Math.max(waitSeconds * 1000, TOAST_DURATION), 12_000),
    });
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
            toastRateLimitOnce(error);

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

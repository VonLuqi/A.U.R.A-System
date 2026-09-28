import axios from 'axios';

/**
 * Axios defaults for SPA session auth (Etapa C §5.8 / Etapa D).
 *
 * Bootstrap:
 * 1. GET /api/csrf-cookie (or any page) → sets XSRF-TOKEN (+ session cookie)
 * 2. withCredentials: true → send cookies on /api/*
 * 3. Axios reads XSRF-TOKEN cookie → sends X-XSRF-TOKEN on mutating requests
 * 4. Accept: application/json → Laravel returns JSON errors (401/422/429)
 */
window.axios = axios;
window.axios.defaults.withCredentials = true;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.headers.common['Accept'] = 'application/json';
window.axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
window.axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';

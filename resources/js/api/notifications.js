/**
 * Notifications API — PLAN_CARTOES_EMPRESTIMOS §6.2.
 *
 * | Método | Path | Resposta |
 * | --- | --- | --- |
 * | GET | `/api/notifications` | `{ data }` |
 * | GET | `/api/notifications/unread-count` | `{ data: { count } }` |
 * | POST | `/api/notifications/{id}/read` | `{ data }` |
 * | POST | `/api/notifications/read-all` | `{ data: { marked } }` |
 *
 * @typedef {{
 *   id: string,
 *   type: string,
 *   data: Record<string, unknown>,
 *   read_at: string|null,
 *   created_at: string|null,
 * }} AppNotification
 */
import api from './client';
import { cleanApiParams } from '../lib/apiParams';

/**
 * @param {{ unread?: boolean|0|1|string, limit?: number }} [params]
 * @param {{ signal?: AbortSignal }} [options]
 * @returns {Promise<{ data: AppNotification[] }>}
 */
export async function listNotifications(params = {}, { signal } = {}) {
    const { data } = await api.get('/api/notifications', {
        params: cleanApiParams(params),
        signal,
    });

    return data;
}

/**
 * @param {{ signal?: AbortSignal }} [options]
 * @returns {Promise<number>}
 */
export async function getUnreadNotificationCount({ signal } = {}) {
    const { data } = await api.get('/api/notifications/unread-count', { signal });

    return Number(data?.data?.count ?? 0);
}

/**
 * @param {string} id
 * @returns {Promise<AppNotification>}
 */
export async function markNotificationRead(id) {
    const { data } = await api.post(`/api/notifications/${id}/read`);

    return data.data;
}

/**
 * @returns {Promise<{ marked: number }>}
 */
export async function markAllNotificationsRead() {
    const { data } = await api.post('/api/notifications/read-all');

    return data.data;
}

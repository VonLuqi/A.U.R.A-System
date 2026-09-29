/**
 * Admin Users API — PLAN_EXPANSAO §2.3 / §8.6.
 *
 * | Método | Path | Resposta |
 * | --- | --- | --- |
 * | GET | `/api/users` | `{ data, meta }` |
 * | POST | `/api/users` | `{ data }` · 201 |
 * | GET | `/api/users/{id}` | `{ data }` |
 * | PATCH | `/api/users/{id}` | `{ data }` (`reset_usage` opcional) |
 * | DELETE | `/api/users/{id}` | `{ data, message }` soft-block |
 *
 * Sem hash de senha no payload. Senha só em create / PATCH opcional.
 *
 * @typedef {'admin'|'subadmin'|'visitor'|'test'} AdminUserRole
 *
 * @typedef {{
 *   max_uploads: number,
 *   max_manual_transactions: number,
 *   max_date_range_days: number,
 *   max_goals: number,
 *   max_aliases: number,
 * }} UserLimits
 *
 * @typedef {{
 *   uploads_used: number,
 *   manual_transactions_used: number,
 *   quota_period_starts_at: string|null,
 *   uploads_remaining: number|null,
 *   manual_transactions_remaining: number|null,
 * }} UserUsage
 *
 * @typedef {{
 *   id: number,
 *   name: string,
 *   email: string,
 *   role: AdminUserRole|string,
 *   is_active: boolean,
 *   limits: UserLimits,
 *   usage: UserUsage,
 *   email_verified_at?: string|null,
 *   created_at?: string|null,
 *   updated_at?: string|null,
 * }} AdminUser
 *
 * @typedef {{
 *   name: string,
 *   email: string,
 *   password: string,
 *   role: 'subadmin'|'visitor'|'test',
 *   is_active?: boolean,
 * }} StoreUserPayload
 *
 * @typedef {{
 *   name?: string,
 *   email?: string,
 *   password?: string,
 *   role?: 'subadmin'|'visitor'|'test',
 *   is_active?: boolean,
 *   reset_usage?: boolean,
 * }} UpdateUserPayload
 */
import api from './client';

/**
 * @param {Record<string, string|number|boolean|undefined>} [params]
 * @param {{ signal?: AbortSignal }} [options]
 * @returns {Promise<{ data: AdminUser[], meta: object }>}
 */
export async function listUsers(params = {}, { signal } = {}) {
    const { data } = await api.get('/api/users', { params, signal });

    return data;
}

/**
 * @param {StoreUserPayload} payload
 * @returns {Promise<AdminUser>}
 */
export async function createUser(payload) {
    const { data } = await api.post('/api/users', payload);

    return data.data;
}

/**
 * @param {number|string} id
 * @param {UpdateUserPayload} payload
 * @returns {Promise<AdminUser>}
 */
export async function updateUser(id, payload) {
    const { data } = await api.patch(`/api/users/${id}`, payload);

    return data.data;
}

/**
 * Soft-block (`is_active=false`).
 * @param {number|string} id
 * @returns {Promise<{ data: AdminUser, message: string }>}
 */
export async function deactivateUser(id) {
    const { data } = await api.delete(`/api/users/${id}`);

    return data;
}

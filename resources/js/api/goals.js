/**
 * Goals API — PLAN_EXPANSAO §7.2 / §8.5.
 *
 * | Método | Path | Resposta |
 * | --- | --- | --- |
 * | GET | `/api/goals` | `{ data, meta }` |
 * | POST | `/api/goals` | `{ data }` · 201 |
 * | GET | `/api/goals/{id}` | `{ data }` |
 * | PATCH | `/api/goals/{id}` | `{ data }` |
 * | DELETE | `/api/goals/{id}` | `{ message }` |
 * | POST | `/api/goals/{id}/recalculate` | `{ data }` |
 *
 * @typedef {'savings'|'debt_payoff'} GoalKind
 * @typedef {'active'|'completed'|'paused'|'cancelled'} GoalStatus
 * @typedef {'manual'|'linked'} GoalProgressMode
 *
 * @typedef {{
 *   id: number,
 *   name: string,
 *   slug?: string,
 *   type?: string,
 *   color?: string|null,
 * }} GoalCategory
 *
 * @typedef {{
 *   id: number,
 *   name: string,
 *   kind: GoalKind|string,
 *   status: GoalStatus|string,
 *   progress_mode: GoalProgressMode|string,
 *   target_amount: string,
 *   current_amount: string,
 *   remaining_amount: string|number,
 *   progress_percent: number,
 *   currency: string,
 *   deadline_on: string|null,
 *   category_id: number|null,
 *   category?: GoalCategory|null,
 *   linked_description_pattern: string|null,
 *   metadata?: object|null,
 *   created_at?: string,
 *   updated_at?: string,
 * }} Goal
 *
 * @typedef {{
 *   name: string,
 *   kind: GoalKind,
 *   target_amount: number|string,
 *   current_amount?: number|string|null,
 *   currency?: string,
 *   deadline_on?: string|null,
 *   category_id?: number|null,
 *   linked_description_pattern?: string|null,
 *   status?: GoalStatus,
 *   progress_mode?: GoalProgressMode,
 * }} GoalWritePayload
 */
import api from './client';

/**
 * @param {Record<string, string|number|undefined>} [params]
 * @param {{ signal?: AbortSignal }} [options]
 * @returns {Promise<{ data: Goal[], meta: object }>}
 */
export async function listGoals(params = {}, { signal } = {}) {
    const { data } = await api.get('/api/goals', { params, signal });

    return data;
}

/**
 * @param {number|string} id
 * @returns {Promise<Goal>}
 */
export async function getGoal(id) {
    const { data } = await api.get(`/api/goals/${id}`);

    return data.data;
}

/**
 * @param {GoalWritePayload} payload
 * @returns {Promise<Goal>}
 */
export async function createGoal(payload) {
    const { data } = await api.post('/api/goals', payload);

    return data.data;
}

/**
 * @param {number|string} id
 * @param {Partial<GoalWritePayload>} payload
 * @returns {Promise<Goal>}
 */
export async function updateGoal(id, payload) {
    const { data } = await api.patch(`/api/goals/${id}`, payload);

    return data.data;
}

/**
 * @param {number|string} id
 * @returns {Promise<{ message: string }>}
 */
export async function deleteGoal(id) {
    const { data } = await api.delete(`/api/goals/${id}`);

    return data;
}

/**
 * @param {number|string} id
 * @returns {Promise<Goal>}
 */
export async function recalculateGoal(id) {
    const { data } = await api.post(`/api/goals/${id}/recalculate`);

    return data.data;
}

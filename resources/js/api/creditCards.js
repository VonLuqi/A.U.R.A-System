/**
 * Credit cards API — PLAN_CARTOES_EMPRESTIMOS §6.2.
 *
 * | Método | Path | Resposta |
 * | --- | --- | --- |
 * | GET | `/api/credit-cards` | `{ data, meta }` |
 * | POST | `/api/credit-cards` | `{ data }` · 201 |
 * | GET | `/api/credit-cards/{id}` | `{ data }` |
 * | PATCH | `/api/credit-cards/{id}` | `{ data }` |
 * | POST | `/api/credit-cards/{id}/link-transactions` | `{ message, data }` |
 * | DELETE | `/api/credit-cards/{id}` | `{ message }` |
 *
 * @typedef {{
 *   id: number,
 *   name: string,
 *   limit_amount: string|null,
 *   currency: string,
 *   closing_day: number,
 *   due_day: number,
 *   next_due_on: string,
 *   next_closing_on: string,
 *   last_four: string|null,
 *   is_active: boolean,
 *   is_default: boolean,
 *   notes: string|null,
 *   created_at?: string,
 *   updated_at?: string,
 * }} CreditCard
 *
 * @typedef {{
 *   name: string,
 *   limit_amount?: number|string|null,
 *   currency?: string,
 *   closing_day: number,
 *   due_day: number,
 *   last_four?: string|null,
 *   is_active?: boolean,
 *   is_default?: boolean,
 *   notes?: string|null,
 * }} CreditCardWritePayload
 */
import api from './client';
import { cleanApiParams } from '../lib/apiParams';

/**
 * @param {Record<string, string|number|boolean|undefined>} [params]
 * @param {{ signal?: AbortSignal }} [options]
 * @returns {Promise<{ data: CreditCard[], meta: object }>}
 */
export async function listCreditCards(params = {}, { signal } = {}) {
    const { data } = await api.get('/api/credit-cards', {
        params: cleanApiParams(params),
        signal,
    });

    return data;
}

/**
 * @param {number|string} id
 * @param {{ signal?: AbortSignal }} [options]
 * @returns {Promise<CreditCard>}
 */
export async function getCreditCard(id, { signal } = {}) {
    const { data } = await api.get(`/api/credit-cards/${id}`, { signal });

    return data.data;
}

/**
 * @param {CreditCardWritePayload} payload
 * @returns {Promise<CreditCard>}
 */
export async function createCreditCard(payload) {
    const { data } = await api.post('/api/credit-cards', payload);

    return data.data;
}

/**
 * @param {number|string} id
 * @param {Partial<CreditCardWritePayload>} payload
 * @returns {Promise<CreditCard>}
 */
export async function updateCreditCard(id, payload) {
    const { data } = await api.patch(`/api/credit-cards/${id}`, payload);

    return data.data;
}

/**
 * @param {number|string} id
 * @param {number[]} transactionIds
 * @returns {Promise<{ message: string, data: { linked: number, skipped: number, limit: number } }>}
 */
export async function linkCreditCardTransactions(id, transactionIds) {
    const { data } = await api.post(`/api/credit-cards/${id}/link-transactions`, {
        transaction_ids: transactionIds,
    });

    return data;
}

/**
 * @param {number|string} id
 * @returns {Promise<{ message: string }>}
 */
export async function deleteCreditCard(id) {
    const { data } = await api.delete(`/api/credit-cards/${id}`);

    return data;
}

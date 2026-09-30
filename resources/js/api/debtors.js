/**
 * Debtors (pessoas) API — feature loans.
 *
 * | Método | Path | Resposta |
 * | --- | --- | --- |
 * | GET | `/api/debtors` | `{ data, meta }` |
 * | POST | `/api/debtors` | `{ data }` · 201 |
 * | PATCH | `/api/debtors/{id}` | `{ data }` |
 * | POST | `/api/debtors/{id}/link-transactions` | `{ message, data }` |
 * | DELETE | `/api/debtors/{id}` | `{ message }` |
 *
 * @typedef {{
 *   id: number,
 *   name: string,
 *   notes: string|null,
 *   open_loans_count?: number,
 *   created_at?: string,
 *   updated_at?: string,
 * }} Debtor
 *
 * @typedef {{
 *   name: string,
 *   notes?: string|null,
 * }} DebtorWritePayload
 */
import api from './client';
import { cleanApiParams } from '../lib/apiParams';

/**
 * @param {Record<string, string|number|boolean|undefined>} [params]
 * @param {{ signal?: AbortSignal }} [options]
 * @returns {Promise<{ data: Debtor[], meta: object }>}
 */
export async function listDebtors(params = {}, { signal } = {}) {
    const { data } = await api.get('/api/debtors', {
        params: cleanApiParams(params),
        signal,
    });

    return data;
}

/**
 * @param {DebtorWritePayload} payload
 * @returns {Promise<Debtor>}
 */
export async function createDebtor(payload) {
    const { data } = await api.post('/api/debtors', payload);

    return data.data;
}

/**
 * @param {number|string} id
 * @param {Partial<DebtorWritePayload>} payload
 * @returns {Promise<Debtor>}
 */
export async function updateDebtor(id, payload) {
    const { data } = await api.patch(`/api/debtors/${id}`, payload);

    return data.data;
}

/**
 * @param {number|string} id
 * @param {number[]} transactionIds
 * @returns {Promise<{ message: string, data: { linked: number, skipped: number, limit: number } }>}
 */
export async function linkDebtorTransactions(id, transactionIds) {
    const { data } = await api.post(`/api/debtors/${id}/link-transactions`, {
        transaction_ids: transactionIds,
    });

    return data;
}

/**
 * @param {number|string} id
 * @returns {Promise<{ message: string }>}
 */
export async function deleteDebtor(id) {
    const { data } = await api.delete(`/api/debtors/${id}`);

    return data;
}

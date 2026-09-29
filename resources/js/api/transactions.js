/**
 * Transactions API — Etapa D §5.1 / PLAN_EXPANSAO §8.2.
 *
 * | Método | Path | Resposta |
 * | --- | --- | --- |
 * | GET | `/api/transactions` | `{ data, meta }` |
 * | POST | `/api/transactions` | `{ data }` · 201 |
 * | PATCH | `/api/transactions/{id}` | `{ data }` |
 * | DELETE | `/api/transactions/{id}` | `{ message }` |
 * | POST | `/api/transactions/{id}/remember-alias` | `{ data, retroactive? }` · 201 |
 *
 * @typedef {{
 *   id: number,
 *   name: string,
 *   slug: string,
 *   type: string,
 *   color: string|null,
 * }} TransactionCategory
 *
 * @typedef {{
 *   id: number,
 *   occurred_on: string,
 *   description: string,
 *   amount: string,
 *   type: 'credit'|'debit'|string,
 *   category: TransactionCategory|null,
 *   user_id: number,
 *   source_kind: 'manual'|'import'|string,
 *   statement_import_id: number|null,
 *   external_id: string|null,
 *   notes: string|null,
 *   editable: boolean,
 *   deletable: boolean,
 * }} Transaction
 *
 * @typedef {{
 *   current_page: number,
 *   per_page: number,
 *   total: number,
 *   last_page: number,
 * }} PaginationMeta
 *
 * @typedef {{
 *   data: Transaction[],
 *   meta: PaginationMeta,
 * }} TransactionsListResponse
 *
 * @typedef {{
 *   occurred_on: string,
 *   amount: number|string,
 *   type: 'credit'|'debit',
 *   description: string,
 *   category_id?: number|null,
 *   notes?: string|null,
 * }} TransactionWritePayload
 */
import api from './client';

/**
 * @param {Record<string, string|number|undefined>} [params]
 * @param {{ signal?: AbortSignal }} [options]
 * @returns {Promise<TransactionsListResponse>}
 */
export async function listTransactions(params = {}, { signal } = {}) {
    const { data } = await api.get('/api/transactions', { params, signal });

    return data;
}

/**
 * @param {TransactionWritePayload} payload
 * @returns {Promise<Transaction>}
 */
export async function createTransaction(payload) {
    const { data } = await api.post('/api/transactions', payload);

    return data.data;
}

/**
 * @param {number|string} id
 * @param {Partial<TransactionWritePayload>} payload
 * @returns {Promise<Transaction>}
 */
export async function updateTransaction(id, payload) {
    const { data } = await api.patch(`/api/transactions/${id}`, payload);

    return data.data;
}

/**
 * @param {number|string} id
 * @returns {Promise<{ message: string }>}
 */
export async function deleteTransaction(id) {
    const { data } = await api.delete(`/api/transactions/${id}`);

    return data;
}

/**
 * @param {number|string} transactionId
 * @param {{
 *   display_name: string,
 *   match_type?: 'exact'|'contains',
 *   match_pattern?: string,
 *   category_id?: number|null,
 *   apply_to_existing?: boolean,
 * }} payload
 * @returns {Promise<{ data: object, retroactive?: object }>}
 */
export async function rememberAlias(transactionId, payload) {
    const { data } = await api.post(
        `/api/transactions/${transactionId}/remember-alias`,
        payload,
    );

    return data;
}

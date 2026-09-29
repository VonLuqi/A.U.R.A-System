/**
 * Transactions API — Etapa D §5.1.
 *
 * `GET /api/transactions` → `{ data: Transaction[], meta }`
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
 *   statement_import_id: number,
 *   external_id: string|null,
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
 */
import api from './client';

/**
 * @param {Record<string, string|number|undefined>} [params]
 *   from, to, type?, category_id?, q?, page?, per_page?, sort?, direction?
 * @param {{ signal?: AbortSignal }} [options]
 * @returns {Promise<TransactionsListResponse>}
 */
export async function listTransactions(params = {}, { signal } = {}) {
    const { data } = await api.get('/api/transactions', { params, signal });

    return data;
}

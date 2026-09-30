/**
 * Loans / cobranças API — PLAN_CARTOES_EMPRESTIMOS §6.2.
 *
 * | Método | Path | Resposta |
 * | --- | --- | --- |
 * | GET | `/api/loans` | `{ data, meta }` |
 * | POST | `/api/loans` | `{ data }` · 201 |
 * | GET | `/api/loans/{id}` | `{ data }` |
 * | PATCH | `/api/loans/{id}` | `{ data }` |
 * | DELETE | `/api/loans/{id}` | `{ message }` |
 * | POST | `/api/loans/{id}/mark-paid` | `{ data }` |
 * | POST | `/api/loans/{id}/cancel` | `{ data }` |
 *
 * @typedef {'cash'|'card_limit'} LoanKind
 * @typedef {'open'|'partial'|'paid'|'cancelled'} LoanStatus
 *
 * @typedef {{
 *   id: number,
 *   name: string,
 * }} LoanCreditCardSummary
 *
 * @typedef {{
 *   id: number,
 *   debtor_name: string,
 *   kind: LoanKind|string,
 *   status: LoanStatus|string,
 *   amount: string,
 *   paid_amount: string,
 *   remaining_amount: string,
 *   currency: string,
 *   lent_on: string|null,
 *   due_on: string|null,
 *   paid_at: string|null,
 *   is_overdue: boolean,
 *   credit_card_id: number|null,
 *   credit_card?: LoanCreditCardSummary|null,
 *   notes: string|null,
 *   created_at?: string,
 *   updated_at?: string,
 * }} Loan
 *
 * @typedef {{
 *   debtor_name: string,
 *   kind: LoanKind,
 *   credit_card_id?: number|null,
 *   amount: number|string,
 *   currency?: string,
 *   lent_on: string,
 *   due_on: string,
 *   notes?: string|null,
 * }} LoanWritePayload
 */
import api from './client';
import { cleanApiParams } from '../lib/apiParams';

/**
 * @param {Record<string, string|number|boolean|undefined>} [params]
 * @param {{ signal?: AbortSignal }} [options]
 * @returns {Promise<{ data: Loan[], meta: object }>}
 */
export async function listLoans(params = {}, { signal } = {}) {
    const { data } = await api.get('/api/loans', {
        params: cleanApiParams(params),
        signal,
    });

    return data;
}

/**
 * @param {number|string} id
 * @param {{ signal?: AbortSignal }} [options]
 * @returns {Promise<Loan>}
 */
export async function getLoan(id, { signal } = {}) {
    const { data } = await api.get(`/api/loans/${id}`, { signal });

    return data.data;
}

/**
 * @param {LoanWritePayload} payload
 * @returns {Promise<Loan>}
 */
export async function createLoan(payload) {
    const { data } = await api.post('/api/loans', payload);

    return data.data;
}

/**
 * @param {number|string} id
 * @param {Partial<LoanWritePayload & { status?: LoanStatus }>} payload
 * @returns {Promise<Loan>}
 */
export async function updateLoan(id, payload) {
    const { data } = await api.patch(`/api/loans/${id}`, payload);

    return data.data;
}

/**
 * @param {number|string} id
 * @returns {Promise<{ message: string }>}
 */
export async function deleteLoan(id) {
    const { data } = await api.delete(`/api/loans/${id}`);

    return data;
}

/**
 * @param {number|string} id
 * @param {{ paid_amount?: number|string|null }} [payload]
 * @returns {Promise<Loan>}
 */
export async function markLoanPaid(id, payload = {}) {
    const { data } = await api.post(`/api/loans/${id}/mark-paid`, payload);

    return data.data;
}

/**
 * @param {number|string} id
 * @returns {Promise<Loan>}
 */
export async function cancelLoan(id) {
    const { data } = await api.post(`/api/loans/${id}/cancel`);

    return data.data;
}

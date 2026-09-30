/**
 * Installment plans API.
 *
 * @typedef {'open'|'partial'|'paid'|'cancelled'} InstallmentPlanStatus
 * @typedef {'open'|'paid'|'cancelled'} InstallmentItemStatus
 *
 * @typedef {{
 *   id: number,
 *   number: number,
 *   amount: string,
 *   due_on: string|null,
 *   status: InstallmentItemStatus|string,
 *   paid_at: string|null,
 *   transaction_id: number|null,
 *   loan_id: number|null,
 * }} InstallmentItem
 *
 * @typedef {{
 *   id: number,
 *   title: string,
 *   total_count: number,
 *   paid_count: number,
 *   installment_amount: string,
 *   open_remaining_total: string,
 *   currency: string,
 *   status: InstallmentPlanStatus|string,
 *   notes: string|null,
 *   credit_card_id: number|null,
 *   credit_card?: { id: number, name: string }|null,
 *   debtor_id: number|null,
 *   debtor?: { id: number, name: string }|null,
 *   items?: InstallmentItem[],
 *   created_at?: string,
 *   updated_at?: string,
 * }} InstallmentPlan
 *
 * @typedef {{
 *   title: string,
 *   total_count: number,
 *   installment_amount: number|string,
 *   first_due_on: string,
 *   credit_card_id?: number|null,
 *   debtor_id?: number|null,
 *   notes?: string|null,
 *   paid_numbers?: number[],
 * }} InstallmentPlanWritePayload
 */
import api from './client';
import { cleanApiParams } from '../lib/apiParams';

/**
 * @param {Record<string, string|number|boolean|undefined>} [params]
 * @param {{ signal?: AbortSignal }} [options]
 */
export async function listInstallmentPlans(params = {}, { signal } = {}) {
    const { data } = await api.get('/api/installment-plans', {
        params: cleanApiParams(params),
        signal,
    });

    return data;
}

/**
 * @param {number|string} id
 * @param {{ signal?: AbortSignal }} [options]
 */
export async function getInstallmentPlan(id, { signal } = {}) {
    const { data } = await api.get(`/api/installment-plans/${id}`, { signal });

    return data.data;
}

/**
 * @param {InstallmentPlanWritePayload} payload
 */
export async function createInstallmentPlan(payload) {
    const { data } = await api.post('/api/installment-plans', payload);

    return data.data;
}

/**
 * @param {number|string} id
 * @param {Partial<{ title: string, notes: string|null, debtor_id: number|null, credit_card_id: number|null }>} payload
 */
export async function updateInstallmentPlan(id, payload) {
    const { data } = await api.patch(`/api/installment-plans/${id}`, payload);

    return data.data;
}

/**
 * @param {number|string} id
 */
export async function cancelInstallmentPlan(id) {
    const { data } = await api.post(`/api/installment-plans/${id}/cancel`);

    return data.data;
}

/**
 * @param {number|string} planId
 * @param {number} number
 */
export async function markInstallmentItemPaid(planId, number) {
    const { data } = await api.post(
        `/api/installment-plans/${planId}/items/${number}/mark-paid`,
    );

    return data.data;
}

/**
 * @param {number|string} planId
 * @param {number} number
 */
export async function markInstallmentItemOpen(planId, number) {
    const { data } = await api.post(
        `/api/installment-plans/${planId}/items/${number}/mark-open`,
    );

    return data.data;
}

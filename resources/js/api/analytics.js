/**
 * Analytics API — Etapa D §5.1.
 *
 * `GET /api/analytics/dashboard` → `{ data: DashboardAnalytics }`
 *
 * @typedef {{
 *   balance: string,
 *   total_income: string,
 *   total_expense: string,
 *   transactions_count: number,
 * }} DashboardCards
 *
 * @typedef {{
 *   period: string,
 *   income: string,
 *   expense: string,
 *   balance: string,
 * }} DashboardSeriesPoint
 *
 * @typedef {{
 *   category_id: number|null,
 *   name: string|null,
 *   color: string|null,
 *   total: string,
 *   count: number,
 *   type?: string,
 * }} DashboardCategoryRow
 *
 * @typedef {{
 *   filters?: { from?: string, to?: string },
 *   cards: DashboardCards,
 *   series: DashboardSeriesPoint[],
 *   by_category: DashboardCategoryRow[],
 * }} DashboardAnalytics
 */
import api from './client';

/**
 * @param {Record<string, string|number|undefined>} [params]
 *   from, to, type?, category_id?, q?, group_by?
 * @param {{ signal?: AbortSignal }} [options]
 * @returns {Promise<DashboardAnalytics>}
 */
export async function fetchDashboardAnalytics(params = {}, { signal } = {}) {
    const { data } = await api.get('/api/analytics/dashboard', { params, signal });

    return data.data;
}

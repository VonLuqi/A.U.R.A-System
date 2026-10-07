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
 *   filters?: {
 *     from?: string,
 *     to?: string,
 *     preset?: 'current_month'|'last_30'|'last_90'|'all'|'custom'|null,
 *     type?: string|null,
 *     category_id?: number|null,
 *     q?: string|null,
 *     group_by?: 'day'|'month',
 *   },
 *   cards: DashboardCards,
 *   series: DashboardSeriesPoint[],
 *   by_category: DashboardCategoryRow[],
 *   goals?: {
 *     items?: Array<object>,
 *     active_count?: number,
 *     completed_count?: number,
 *     paused_count?: number,
 *     goals_used?: number,
 *     goals_remaining?: number|null,
 *     cards?: object,
 *   },
 * }} DashboardAnalytics
 */
import api from './client';

/**
 * @param {Record<string, string|number|undefined>} [params]
 *   from, to, preset?, type?, category_id?, q?, group_by?
 *   Contrato: PLAN_EXPANSAO §6.2 (`?from=YYYY-MM-DD&to=YYYY-MM-DD&preset=custom`)
 * @param {{ signal?: AbortSignal }} [options]
 * @returns {Promise<DashboardAnalytics>}
 */
export async function fetchDashboardAnalytics(params = {}, { signal } = {}) {
    const { data } = await api.get('/api/analytics/dashboard', { params, signal });

    return data.data;
}

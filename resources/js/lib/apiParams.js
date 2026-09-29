/**
 * Limpa params vazios para query string da API.
 * @param {Record<string, unknown>} params
 */
export function cleanApiParams(params) {
    const out = {};

    Object.entries(params).forEach(([key, value]) => {
        if (value === '' || value === null || value === undefined) {
            return;
        }

        out[key] = value;
    });

    return out;
}

/**
 * Params de analytics (sem paginação/sort).
 * @param {Record<string, unknown>} filters
 */
export function toAnalyticsParams(filters) {
    return cleanApiParams({
        from: filters.from,
        to: filters.to,
        type: filters.type,
        category_id: filters.category_id,
        q: filters.q,
        group_by: filters.group_by,
    });
}

/**
 * Params de listagem de transações.
 * @param {Record<string, unknown>} filters
 */
export function toTransactionsParams(filters) {
    return cleanApiParams({
        from: filters.from,
        to: filters.to,
        type: filters.type,
        category_id: filters.category_id,
        q: filters.q,
        page: filters.page,
        per_page: filters.per_page,
        sort: filters.sort,
        direction: filters.direction,
    });
}

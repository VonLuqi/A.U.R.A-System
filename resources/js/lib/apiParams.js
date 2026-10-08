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
 * Contrato PLAN_EXPANSAO §6.2: from/to + preset + group_by.
 * @param {Record<string, unknown>} filters
 */
export function toAnalyticsParams(filters) {
    const creditCardIds = Array.isArray(filters.credit_card_ids)
        ? filters.credit_card_ids
        : [];

    return cleanApiParams({
        from: filters.from,
        to: filters.to,
        preset: filters.preset,
        cycle_offset: filters.cycle_offset,
        type: filters.type,
        category_id: filters.category_id,
        credit_card_ids: creditCardIds.length > 0 ? creditCardIds : undefined,
        include_uncarded: creditCardIds.length > 0 && filters.include_uncarded ? 1 : undefined,
        debtor_id: filters.debtor_id,
        q: filters.q,
        group_by: filters.group_by,
    });
}

/**
 * Params de listagem de transações.
 * Contrato PLAN_EXPANSAO §6.2: from/to + preset (+ paginação).
 * @param {Record<string, unknown>} filters
 */
export function toTransactionsParams(filters) {
    const creditCardIds = Array.isArray(filters.credit_card_ids)
        ? filters.credit_card_ids
        : [];

    return cleanApiParams({
        from: filters.from,
        to: filters.to,
        preset: filters.preset,
        cycle_offset: filters.cycle_offset,
        type: filters.type,
        category_id: filters.category_id,
        credit_card_ids: creditCardIds.length > 0 ? creditCardIds : undefined,
        include_uncarded: creditCardIds.length > 0 && filters.include_uncarded ? 1 : undefined,
        debtor_id: filters.debtor_id,
        q: filters.q,
        page: filters.page,
        per_page: filters.per_page,
        sort: filters.sort,
        direction: filters.direction,
    });
}

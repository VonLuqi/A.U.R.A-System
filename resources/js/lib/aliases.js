/**
 * Alias labels / constants — PLAN_EXPANSAO §8.7.
 * `ALIAS_RETROACTIVE_LIMIT` espelha `config('aura.aliases.retroactive_limit')`
 * / `AURA_ALIAS_RETROACTIVE_LIMIT` (default 500).
 */

export const ALIAS_RETROACTIVE_LIMIT = 500;

export const ALIAS_MATCH_TYPES = Object.freeze({
    exact: 'exact',
    contains: 'contains',
    starts_with: 'starts_with',
    regex: 'regex',
});

export const ALIAS_MATCH_TYPE_LABELS = {
    exact: 'Exato',
    contains: 'Contém',
    starts_with: 'Começa com',
    regex: 'Regex',
};

/**
 * Copy do aviso quando `apply_to_existing` está ligado.
 * @param {number} [limit]
 * @returns {string}
 */
export function applyToExistingWarning(limit = ALIAS_RETROACTIVE_LIMIT) {
    return `Serão analisados no máximo ${limit} lançamentos mais recentes. A aplicação é síncrona e pode demorar um pouco.`;
}

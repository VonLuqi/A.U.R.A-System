/**
 * Admin user labels — PLAN_EXPANSAO §8.6.
 */

export const ASSIGNABLE_ROLES = Object.freeze(['subadmin', 'visitor', 'test']);

export const USER_ROLE_LABELS = {
    admin: 'Admin',
    subadmin: 'Subadmin',
    visitor: 'Visitante',
    test: 'Teste',
};

/**
 * @param {number|null|undefined} used
 * @param {number|null|undefined} limit 0 = unlimited
 * @returns {string}
 */
export function formatQuota(used, limit) {
    const usedSafe = Number(used) || 0;

    if (limit == null || Number(limit) === 0) {
        return `${usedSafe} / ∞`;
    }

    return `${usedSafe} / ${Number(limit)}`;
}

/**
 * @param {string|null|undefined} iso
 * @returns {string}
 */
export function formatUserDate(iso) {
    if (!iso) {
        return '—';
    }

    const day = String(iso).slice(0, 10);
    const match = day.match(/^(\d{4})-(\d{2})-(\d{2})$/);

    if (!match) {
        return String(iso);
    }

    return `${match[3]}/${match[2]}/${match[1]}`;
}

/**
 * Auth helpers — PLAN_EXPANSAO §8.1.
 *
 * Contrato `AuthUser` (GET /api/user · POST /api/login):
 * `{ id, name, email, role, is_active, limits, usage, abilities[], features }`
 *
 * @typedef {'admin'|'subadmin'|'visitor'|'test'} UserRole
 *
 * @typedef {'users.manage'|'transactions.manage'|'statements.upload'|'goals.manage'|'aliases.manage'} Ability
 *
 * @typedef {{
 *   max_uploads: number,
 *   max_manual_transactions: number,
 *   max_date_range_days: number,
 *   max_goals: number,
 *   max_aliases: number,
 * }} AuthLimits
 *
 * @typedef {{
 *   uploads_used: number,
 *   manual_transactions_used: number,
 *   quota_period_starts_at: string|null,
 *   uploads_remaining: number|null,
 *   manual_transactions_remaining: number|null,
 *   goals_used: number,
 *   goals_remaining: number|null,
 *   aliases_used: number,
 *   aliases_remaining: number|null,
 * }} AuthUsage
 *
 * @typedef {{
 *   manual_transactions: boolean,
 *   goals: boolean,
 *   aliases: boolean,
 *   credit_card_upload: boolean,
 *   admin_users: boolean,
 * }} AuthFeatures
 *
 * @typedef {{
 *   id: number,
 *   name: string,
 *   email: string,
 *   role: UserRole|string,
 *   is_active: boolean,
 *   limits: AuthLimits,
 *   usage: AuthUsage,
 *   abilities: Ability[]|string[],
 *   features?: AuthFeatures,
 * }} AuthUser
 *
 * @typedef {{
 *   to: string,
 *   label: string,
 *   end?: boolean,
 *   ability?: Ability|null,
 *   roles?: UserRole[],
 * }} NavItem
 */

export const ROLES = Object.freeze({
    admin: 'admin',
    subadmin: 'subadmin',
    visitor: 'visitor',
    test: 'test',
});

export const ABILITIES = Object.freeze({
    usersManage: 'users.manage',
    transactionsManage: 'transactions.manage',
    statementsUpload: 'statements.upload',
    goalsManage: 'goals.manage',
    aliasesManage: 'aliases.manage',
});

/**
 * @param {AuthUser|null|undefined} user
 * @param {Ability|string} ability
 * @returns {boolean}
 */
export function can(user, ability) {
    if (!user || !ability) {
        return false;
    }

    if (Array.isArray(user.abilities)) {
        return user.abilities.includes(ability);
    }

    return false;
}

/**
 * @param {AuthUser|null|undefined} user
 * @param {UserRole|string|Array<UserRole|string>} role
 * @returns {boolean}
 */
export function isRole(user, role) {
    if (!user?.role) {
        return false;
    }

    const roles = Array.isArray(role) ? role : [role];

    return roles.includes(user.role);
}

/**
 * @param {AuthUser|null|undefined} user
 * @returns {boolean}
 */
export function isAdmin(user) {
    return isRole(user, ROLES.admin);
}

/**
 * Feature flag from AuthUser payload (PLAN_EXPANSAO §9.3).
 *
 * @param {AuthUser|null|undefined} user
 * @param {keyof AuthFeatures|string} feature
 * @returns {boolean}
 */
export function featureEnabled(user, feature) {
    if (!user || !feature) {
        return false;
    }

    if (user.features && typeof user.features === 'object') {
        return Boolean(user.features[feature]);
    }

    // Legacy payloads without features: treat as enabled.
    return true;
}

/**
 * Inventário de nav SPA (§8.1). Filtrar com `navItemsFor(user)`.
 * @type {NavItem[]}
 */
export const NAV_CATALOG = [
    { to: '/dashboard', label: 'Visão geral', end: true },
    { to: '/upload', label: 'Importar', end: false, ability: ABILITIES.statementsUpload },
    { to: '/goals', label: 'Metas', end: false, ability: ABILITIES.goalsManage },
    { to: '/aliases', label: 'Apelidos', end: false, ability: ABILITIES.aliasesManage },
    {
        to: '/admin/users',
        label: 'Usuários',
        end: false,
        ability: ABILITIES.usersManage,
        roles: [ROLES.admin],
    },
];

/**
 * @param {AuthUser|null|undefined} user
 * @returns {NavItem[]}
 */
export function navItemsFor(user) {
    if (!user) {
        return [];
    }

    return NAV_CATALOG.filter((item) => {
        if (item.roles?.length && !isRole(user, item.roles)) {
            return false;
        }

        if (item.ability && !can(user, item.ability)) {
            return false;
        }

        return true;
    });
}

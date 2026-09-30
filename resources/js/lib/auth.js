/**
 * Auth helpers — PLAN_EXPANSAO §8.1 · Etapa I (avatar_url).
 *
 * Contrato `AuthUser` (GET /api/user · POST /api/login · Profile API):
 * `{ id, name, email, avatar_url, role, is_active, limits, usage, abilities[], features }`
 *
 * Self-service (Etapa I): nome / senha / avatar via ProfileController.
 * `email` é **somente leitura** no self-service (alteração só via Admin, se houver).
 *
 * @typedef {'admin'|'subadmin'|'visitor'|'test'} UserRole
 *
 * @typedef {'users.manage'|'transactions.manage'|'statements.upload'|'goals.manage'|'aliases.manage'|'credit_cards.manage'|'loans.manage'|'notifications.read'} Ability
 *
 * @typedef {{
 *   max_uploads: number,
 *   max_manual_transactions: number,
 *   max_date_range_days: number,
 *   max_goals: number,
 *   max_aliases: number,
 *   max_credit_cards?: number,
 *   max_loans?: number,
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
 *   credit_cards_used?: number,
 *   credit_cards_remaining?: number|null,
 *   loans_used?: number,
 *   loans_remaining?: number|null,
 * }} AuthUsage
 *
 * @typedef {{
 *   manual_transactions: boolean,
 *   goals: boolean,
 *   aliases: boolean,
 *   credit_card_upload: boolean,
 *   admin_users: boolean,
 *   credit_cards?: boolean,
 *   loans?: boolean,
 *   notifications?: boolean,
 * }} AuthFeatures
 *
 * @typedef {{
 *   id: number,
 *   name: string,
 *   email: string,
 *   avatar_url?: string|null,
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
 *   feature?: keyof AuthFeatures|string|null,
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
    creditCardsManage: 'credit_cards.manage',
    loansManage: 'loans.manage',
    notificationsRead: 'notifications.read',
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
 * Iniciais para fallback visual quando `avatar_url` é null (Etapa I §3.2).
 * Preferência: primeiras letras de até 2 palavras do nome; senão 1ª letra do e-mail; senão "?".
 *
 * @param {AuthUser|{ name?: string, email?: string }|null|undefined} user
 * @returns {string}
 */
export function userInitials(user) {
    const name = typeof user?.name === 'string' ? user.name.trim() : '';

    if (name !== '') {
        const parts = name.split(/\s+/).filter(Boolean);

        if (parts.length >= 2) {
            return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
        }

        return parts[0].slice(0, 2).toUpperCase();
    }

    const email = typeof user?.email === 'string' ? user.email.trim() : '';

    if (email !== '') {
        return email[0].toUpperCase();
    }

    return '?';
}

/**
 * Inventário de nav SPA (§8.1 / PLAN_CARTOES_EMPRESTIMOS §6.1).
 * Filtrar com `navItemsFor(user)` (ability + feature flag + roles).
 * @type {NavItem[]}
 */
export const NAV_CATALOG = [
    { to: '/dashboard', label: 'Visão geral', end: true },
    { to: '/transactions', label: 'Movimentações', end: false },
    { to: '/upload', label: 'Importar', end: false, ability: ABILITIES.statementsUpload },
    { to: '/goals', label: 'Metas', end: false, ability: ABILITIES.goalsManage, feature: 'goals' },
    { to: '/aliases', label: 'Apelidos', end: false, ability: ABILITIES.aliasesManage, feature: 'aliases' },
    {
        to: '/cards',
        label: 'Cartões',
        end: false,
        ability: ABILITIES.creditCardsManage,
        feature: 'credit_cards',
    },
    {
        to: '/loans',
        label: 'Devedores',
        end: false,
        ability: ABILITIES.loansManage,
        feature: 'loans',
    },
    {
        to: '/admin/users',
        label: 'Usuários',
        end: false,
        ability: ABILITIES.usersManage,
        feature: 'admin_users',
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

        if (item.feature && !featureEnabled(user, item.feature)) {
            return false;
        }

        if (item.ability && !can(user, item.ability)) {
            return false;
        }

        return true;
    });
}

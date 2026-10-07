/**
 * Labels / helpers de categorias (CRUD gestão).
 */

export const CATEGORY_TYPES = {
    income: 'income',
    expense: 'expense',
    transfer: 'transfer',
};

export const CATEGORY_TYPE_LABELS = {
    income: 'Entrada',
    expense: 'Saída',
    transfer: 'Transferência',
};

export const DEFAULT_CATEGORY_COLOR = '#DCCFFF';

/**
 * @param {string|null|undefined} type
 * @returns {string}
 */
export function categoryTypeLabel(type) {
    return CATEGORY_TYPE_LABELS[type] ?? String(type ?? '—');
}

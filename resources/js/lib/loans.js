/**
 * Loan / cobrança labels — PLAN_CARTOES_EMPRESTIMOS §6.2.
 */

export const LOAN_KINDS = Object.freeze({
    cash: 'cash',
    card_limit: 'card_limit',
});

export const LOAN_STATUSES = Object.freeze({
    open: 'open',
    partial: 'partial',
    paid: 'paid',
    cancelled: 'cancelled',
});

export const LOAN_KIND_LABELS = {
    cash: 'Dinheiro',
    card_limit: 'Limite do cartão',
};

export const LOAN_STATUS_LABELS = {
    open: 'Em aberto',
    partial: 'Parcial',
    paid: 'Pago',
    cancelled: 'Cancelado',
};

/**
 * @param {string|null|undefined} kind
 * @returns {string}
 */
export function loanKindLabel(kind) {
    if (!kind) {
        return '—';
    }

    return LOAN_KIND_LABELS[kind] ?? String(kind);
}

/**
 * @param {string|null|undefined} status
 * @returns {string}
 */
export function loanStatusLabel(status) {
    if (!status) {
        return '—';
    }

    return LOAN_STATUS_LABELS[status] ?? String(status);
}

/**
 * @param {{ status?: string, is_overdue?: boolean, due_on?: string|null }} loan
 * @returns {boolean}
 */
export function isLoanOverdue(loan) {
    if (!loan) {
        return false;
    }

    if (typeof loan.is_overdue === 'boolean') {
        return loan.is_overdue;
    }

    const status = loan.status;
    if (status !== LOAN_STATUSES.open && status !== LOAN_STATUSES.partial) {
        return false;
    }

    if (!loan.due_on) {
        return false;
    }

    return String(loan.due_on) < new Date().toISOString().slice(0, 10);
}

/**
 * @param {unknown} raw
 * @returns {{ ok: true, value: string } | { ok: false, message: string }}
 */
export function parseLoanAmount(raw) {
    const normalized = String(raw ?? '')
        .trim()
        .replace(/\s/g, '')
        .replace(',', '.');

    if (!normalized) {
        return { ok: false, message: 'Informe o valor.' };
    }

    if (!/^\d+(\.\d{1,2})?$/.test(normalized)) {
        return { ok: false, message: 'Use até duas casas decimais.' };
    }

    const value = Number(normalized);

    if (!(value > 0)) {
        return { ok: false, message: 'O valor deve ser maior que zero.' };
    }

    return { ok: true, value: value.toFixed(2) };
}

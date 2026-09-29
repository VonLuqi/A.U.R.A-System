/**
 * Goal labels — PLAN_EXPANSAO §8.5.
 */

export const GOAL_KINDS = Object.freeze({
    savings: 'savings',
    debt_payoff: 'debt_payoff',
});

export const GOAL_STATUSES = Object.freeze({
    active: 'active',
    completed: 'completed',
    paused: 'paused',
    cancelled: 'cancelled',
});

export const GOAL_PROGRESS_MODES = Object.freeze({
    manual: 'manual',
    linked: 'linked',
});

export const GOAL_KIND_LABELS = {
    savings: 'Poupança',
    debt_payoff: 'Amortizar dívida',
};

export const GOAL_STATUS_LABELS = {
    active: 'Ativa',
    completed: 'Concluída',
    paused: 'Pausada',
    cancelled: 'Cancelada',
};

export const GOAL_PROGRESS_MODE_LABELS = {
    manual: 'Manual',
    linked: 'Vinculada',
};

/**
 * @param {string} raw
 * @returns {{ ok: true, value: string } | { ok: false, message: string }}
 */
export function parseGoalAmount(raw, { allowZero = false } = {}) {
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

    if (allowZero) {
        if (value < 0) {
            return { ok: false, message: 'O valor não pode ser negativo.' };
        }
    } else if (!(value > 0)) {
        return { ok: false, message: 'O valor deve ser maior que zero.' };
    }

    return { ok: true, value: value.toFixed(2) };
}

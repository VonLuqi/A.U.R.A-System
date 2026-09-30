/**
 * Credit card labels / helpers — PLAN_CARTOES_EMPRESTIMOS §6.2.
 */

/**
 * @param {unknown} raw
 * @returns {{ ok: true, value: number } | { ok: false, message: string }}
 */
export function parseDayOfMonth(raw) {
    const trimmed = String(raw ?? '').trim();

    if (!trimmed) {
        return { ok: false, message: 'Informe o dia (1 a 31).' };
    }

    if (!/^\d{1,2}$/.test(trimmed)) {
        return { ok: false, message: 'Use um número inteiro entre 1 e 31.' };
    }

    const value = Number.parseInt(trimmed, 10);

    if (value < 1 || value > 31) {
        return { ok: false, message: 'O dia deve ser entre 1 e 31.' };
    }

    return { ok: true, value };
}

/**
 * @param {unknown} raw
 * @returns {{ ok: true, value: string|null } | { ok: false, message: string }}
 */
export function parseLastFour(raw) {
    const trimmed = String(raw ?? '').trim();

    if (!trimmed) {
        return { ok: true, value: null };
    }

    if (!/^\d{4}$/.test(trimmed)) {
        return { ok: false, message: 'Informe exatamente 4 dígitos.' };
    }

    return { ok: true, value: trimmed };
}

/**
 * @param {unknown} raw
 * @param {{ allowEmpty?: boolean }} [options]
 * @returns {{ ok: true, value: string|null } | { ok: false, message: string }}
 */
export function parseOptionalAmount(raw, { allowEmpty = true } = {}) {
    const normalized = String(raw ?? '')
        .trim()
        .replace(/\s/g, '')
        .replace(',', '.');

    if (!normalized) {
        if (allowEmpty) {
            return { ok: true, value: null };
        }

        return { ok: false, message: 'Informe o valor.' };
    }

    if (!/^\d+(\.\d{1,2})?$/.test(normalized)) {
        return { ok: false, message: 'Use até duas casas decimais.' };
    }

    const value = Number(normalized);

    if (value < 0) {
        return { ok: false, message: 'O valor não pode ser negativo.' };
    }

    return { ok: true, value: value.toFixed(2) };
}

/**
 * @param {{ is_active?: boolean }} card
 * @returns {string}
 */
export function creditCardStatusLabel(card) {
    return card?.is_active === false ? 'Inativo' : 'Ativo';
}

/**
 * Date presets pt-BR / America/Sao_Paulo — Etapa D §4.2.
 */

function pad(n) {
    return String(n).padStart(2, '0');
}

function toIsoDate(date) {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function startOfDay(date) {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
}

/** @returns {{ from: string, to: string }} */
export function currentMonthRange(now = new Date()) {
    const from = new Date(now.getFullYear(), now.getMonth(), 1);
    const to = new Date(now.getFullYear(), now.getMonth() + 1, 0);

    return { from: toIsoDate(from), to: toIsoDate(to) };
}

/** @param {number} days */
export function lastNDaysRange(days, now = new Date()) {
    const to = startOfDay(now);
    const from = new Date(to);
    from.setDate(from.getDate() - (days - 1));

    return { from: toIsoDate(from), to: toIsoDate(to) };
}

/**
 * Inclusive day count between Y-m-d strings.
 * @param {string} from
 * @param {string} to
 */
export function daysBetween(from, to) {
    const a = new Date(`${from}T00:00:00`);
    const b = new Date(`${to}T00:00:00`);

    if (Number.isNaN(a.getTime()) || Number.isNaN(b.getTime())) {
        return 0;
    }

    const diff = Math.round((b.getTime() - a.getTime()) / 86400000);

    return Math.abs(diff) + 1;
}

/**
 * @param {string} from
 * @param {string} to
 * @returns {'day'|'month'}
 */
export function resolveGroupBy(from, to) {
    return daysBetween(from, to) <= 45 ? 'day' : 'month';
}

export const PERIOD_PRESET_IDS = {
    current_month: 'current_month',
    last_30: 'last_30',
    last_90: 'last_90',
};

export const PERIOD_PRESETS = {
    current_month: () => currentMonthRange(),
    last_30: () => lastNDaysRange(30),
    last_90: () => lastNDaysRange(90),
};

export const PERIOD_PRESET_LABELS = {
    current_month: 'Este mês',
    last_30: 'Últimos 30 dias',
    last_90: 'Últimos 90 dias',
};

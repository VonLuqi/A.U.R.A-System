/**
 * Date presets + group_by — Etapa D §4.2 / PLAN_EXPANSAO §6.1–6.2 / §8.3.
 *
 * Contrato API (query string compartilhado com analytics + transactions):
 * - `from` / `to`: ISO `YYYY-MM-DD` (juntos; omitidos → mês corrente no backend)
 * - `preset`: `current_month` | `last_30` | `last_90` | `custom`
 *   - named: backend recalcula bounds; frontend grava `from`+`to`+`preset` na URL
 *   - `custom`: exige `from`+`to` (ex.: `?from=2026-08-01&to=2026-08-31&preset=custom`)
 * - `group_by` (só analytics): `day` | `month`; omitido → ≤45 dias inclusivos → `day`
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

/** @typedef {'current_month'|'last_30'|'last_90'|'custom'} PeriodPresetId */

export const PERIOD_PRESET_IDS = {
    current_month: 'current_month',
    last_30: 'last_30',
    last_90: 'last_90',
    custom: 'custom',
};

/** @type {Record<Exclude<PeriodPresetId, 'custom'>, () => { from: string, to: string }>} */
export const PERIOD_PRESETS = {
    current_month: () => currentMonthRange(),
    last_30: () => lastNDaysRange(30),
    last_90: () => lastNDaysRange(90),
};

export const PERIOD_PRESET_LABELS = {
    current_month: 'Este mês',
    last_30: 'Últimos 30 dias',
    last_90: 'Últimos 90 dias',
    custom: 'Personalizado',
};

/**
 * @param {string|null|undefined} raw
 * @returns {PeriodPresetId|null}
 */
export function normalizePeriodPreset(raw) {
    const value = String(raw ?? '').trim().toLowerCase();

    if (!value) {
        return null;
    }

    return Object.values(PERIOD_PRESET_IDS).includes(value) ? /** @type {PeriodPresetId} */ (value) : null;
}

/**
 * @param {string|null|undefined} iso YYYY-MM-DD
 * @returns {Date|null}
 */
export function parseIsoDate(iso) {
    if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
        return null;
    }

    const date = new Date(`${iso}T00:00:00`);

    return Number.isNaN(date.getTime()) ? null : date;
}

/**
 * @param {Date} date
 * @returns {string}
 */
export function formatIsoDate(date) {
    return toIsoDate(date);
}

/**
 * Label PT-BR curto para intervalo (ex.: `01/08/2026 – 31/08/2026`).
 * @param {string} from
 * @param {string} to
 * @returns {string}
 */
export function formatRangeLabel(from, to) {
    const fmt = (iso) => {
        const parts = String(iso).split('-');
        if (parts.length !== 3) {
            return iso;
        }

        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    };

    return `${fmt(from)} – ${fmt(to)}`;
}

/**
 * Limite inclusivo do papel (`0` / ausente → ilimitado).
 * @param {{ limits?: { max_date_range_days?: number|null } }|null|undefined} user
 * @returns {number|null}
 */
export function maxDateRangeDaysFor(user) {
    const raw = user?.limits?.max_date_range_days;

    if (raw == null) {
        return null;
    }

    const max = Number(raw);

    if (!Number.isFinite(max) || max <= 0) {
        return null;
    }

    return max;
}

/**
 * @param {string} from
 * @param {string} to
 * @param {number|null|undefined} maxDays
 * @returns {boolean}
 */
export function isWithinDateRangeLimit(from, to, maxDays) {
    if (maxDays == null || maxDays <= 0) {
        return true;
    }

    return daysBetween(from, to) <= maxDays;
}

/**
 * Mensagem alinhada ao backend `WithinRoleDateRangeLimit`.
 * @param {number} maxDays
 * @param {number} requestedDays
 * @returns {string}
 */
export function dateRangeLimitMessage(maxDays, requestedDays) {
    return `O intervalo máximo permitido para o seu perfil é de ${maxDays} dias (solicitado: ${requestedDays}).`;
}

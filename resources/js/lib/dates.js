/**
 * Date presets + group_by — Etapa D §4.2 / PLAN_EXPANSAO §6.1–6.2 / §8.3.
 *
 * Contrato API (query string compartilhado com analytics + transactions):
 * - `from` / `to`: ISO `YYYY-MM-DD` (juntos; omitidos → mês corrente no backend)
 * - `preset`: `current_month` | `last_30` | `last_90` | `all` | `custom` | `my_cycle`
 *   - named: backend recalcula bounds; frontend grava `from`+`to`+`preset` na URL
 *   - `all`: sem from/to (histórico completo; só papéis com intervalo ilimitado)
 *   - `custom`: exige `from`+`to` (ex.: `?from=2026-08-01&to=2026-08-31&preset=custom`)
 *   - `my_cycle`: `cycle_offset` + dias de perfil + `type`
 * - `group_by` (só analytics): `day` | `month`; omitido → ≤45 dias inclusivos → `day`; all → month
 */

export const DEFAULT_EXPENSE_CYCLE_DAY = 6;
export const DEFAULT_INCOME_CYCLE_DAY = 12;

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
    if (!from || !to) {
        return 'month';
    }

    return daysBetween(from, to) <= 45 ? 'day' : 'month';
}

/** @typedef {'current_month'|'last_30'|'last_90'|'all'|'custom'|'my_cycle'} PeriodPresetId */

export const PERIOD_PRESET_IDS = {
    current_month: 'current_month',
    last_30: 'last_30',
    last_90: 'last_90',
    all: 'all',
    custom: 'custom',
    my_cycle: 'my_cycle',
};

/** @type {Record<Exclude<PeriodPresetId, 'custom'|'my_cycle'>, () => { from: string, to: string }>} */
export const PERIOD_PRESETS = {
    current_month: () => currentMonthRange(),
    last_30: () => lastNDaysRange(30),
    last_90: () => lastNDaysRange(90),
    all: () => ({ from: '', to: '' }),
};

export const PERIOD_PRESET_LABELS = {
    current_month: 'Este mês',
    last_30: 'Últimos 30 dias',
    last_90: 'Últimos 90 dias',
    all: 'Todo o histórico',
    custom: 'Personalizado',
    my_cycle: 'Meu ciclo',
};

/**
 * @param {string|null|undefined} preset
 * @returns {boolean}
 */
export function isCyclePreset(preset) {
    return preset === PERIOD_PRESET_IDS.my_cycle;
}

/**
 * Clamp day-of-month to the last valid day of that month.
 * @param {number} year
 * @param {number} monthIndex 0–11
 * @param {number} day
 * @returns {Date}
 */
function dateOnMonth(year, monthIndex, day) {
    const daysInMonth = new Date(year, monthIndex + 1, 0).getDate();
    const clamped = Math.min(Math.max(1, day), daysInMonth);

    return new Date(year, monthIndex, clamped);
}

/**
 * Inclusive cycle day D → next month D. Offset 0 = cycle containing `now`.
 *
 * @param {number} day
 * @param {number} [offset]
 * @param {Date} [now]
 * @returns {{ from: string, to: string }}
 */
export function cycleDayRange(day, offset = 0, now = new Date()) {
    const safeDay = Math.max(1, Math.min(31, Number(day) || 1));
    const safeOffset = Math.max(-120, Math.min(120, Number(offset) || 0));
    const today = startOfDay(now);

    let start = dateOnMonth(today.getFullYear(), today.getMonth(), safeDay);
    if (start.getTime() > today.getTime()) {
        const prev = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        start = dateOnMonth(prev.getFullYear(), prev.getMonth(), safeDay);
    }

    if (safeOffset !== 0) {
        const shifted = new Date(start.getFullYear(), start.getMonth() + safeOffset, 1);
        start = dateOnMonth(shifted.getFullYear(), shifted.getMonth(), safeDay);
    }

    const endBase = new Date(start.getFullYear(), start.getMonth() + 1, 1);
    const end = dateOnMonth(endBase.getFullYear(), endBase.getMonth(), safeDay);

    return { from: toIsoDate(start), to: toIsoDate(end) };
}

/**
 * @param {''|'credit'|'debit'|null|undefined} type
 * @param {number} expenseOrClosingDay
 * @param {number} incomeOrDueDay
 * @returns {number}
 */
export function cycleDayForType(type, expenseOrClosingDay, incomeOrDueDay) {
    if (type === 'credit') {
        return Math.max(1, Math.min(31, Number(incomeOrDueDay) || DEFAULT_INCOME_CYCLE_DAY));
    }

    return Math.max(1, Math.min(31, Number(expenseOrClosingDay) || DEFAULT_EXPENSE_CYCLE_DAY));
}

/**
 * @param {{
 *   preset: PeriodPresetId|string,
 *   type?: ''|'credit'|'debit'|null,
 *   cycleOffset?: number,
 *   user?: { expense_cycle_day?: number|null, income_cycle_day?: number|null }|null,
 *   now?: Date,
 * }} args
 * @returns {{ from: string, to: string }|null}
 */
export function resolveCycleRange({
    preset,
    type = '',
    cycleOffset = 0,
    user = null,
    now = new Date(),
}) {
    if (preset !== PERIOD_PRESET_IDS.my_cycle) {
        return null;
    }

    const day = cycleDayForType(
        type,
        user?.expense_cycle_day ?? DEFAULT_EXPENSE_CYCLE_DAY,
        user?.income_cycle_day ?? DEFAULT_INCOME_CYCLE_DAY,
    );

    return cycleDayRange(day, cycleOffset, now);
}

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
 * ISO → display pt-BR (`2026-09-27` → `27/09/2026`).
 * @param {string|null|undefined} iso YYYY-MM-DD
 * @returns {string}
 */
export function formatBrDate(iso) {
    const date = parseIsoDate(iso);

    if (!date) {
        return '';
    }

    return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()}`;
}

/**
 * Aceita `dd/mm/aaaa`, `ddmmaaaa` ou ISO `YYYY-MM-DD`.
 * @param {string|null|undefined} raw
 * @returns {string|null} YYYY-MM-DD ou null se inválido
 */
export function parseBrDate(raw) {
    const trimmed = String(raw ?? '').trim();

    if (!trimmed) {
        return null;
    }

    if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
        return parseIsoDate(trimmed) ? trimmed : null;
    }

    const digits = trimmed.replace(/\D/g, '');

    if (digits.length !== 8) {
        return null;
    }

    const day = Number.parseInt(digits.slice(0, 2), 10);
    const month = Number.parseInt(digits.slice(2, 4), 10);
    const year = Number.parseInt(digits.slice(4, 8), 10);

    if (year < 1000 || month < 1 || month > 12 || day < 1 || day > 31) {
        return null;
    }

    const date = new Date(year, month - 1, day);

    if (
        date.getFullYear() !== year ||
        date.getMonth() !== month - 1 ||
        date.getDate() !== day
    ) {
        return null;
    }

    return toIsoDate(date);
}

/**
 * Máscara progressiva enquanto digita (`2709` → `27/09`).
 * @param {string} raw
 * @returns {string}
 */
export function maskBrDateInput(raw) {
    const digits = String(raw ?? '')
        .replace(/\D/g, '')
        .slice(0, 8);

    if (digits.length <= 2) {
        return digits;
    }

    if (digits.length <= 4) {
        return `${digits.slice(0, 2)}/${digits.slice(2)}`;
    }

    return `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`;
}

/**
 * Label PT-BR curto para intervalo (ex.: `01/08/2026 – 31/08/2026`).
 * @param {string} from
 * @param {string} to
 * @returns {string}
 */
export function formatRangeLabel(from, to) {
    if (!from || !to) {
        return PERIOD_PRESET_LABELS.all;
    }

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

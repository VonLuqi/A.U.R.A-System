/**
 * Formatação pt-BR — Etapa D §4.9.
 * Valores monetários da API chegam como string decimal (`"89.90"`) —
 * parse explícito via `parseDecimal`; nunca `Number(value)` cego.
 */

const moneyFormatter = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});

/**
 * Parse seguro de decimal API / input.
 * Aceita number finito ou string `"89.90"` / `"89,90"`.
 * @param {string|number|null|undefined} value
 * @returns {number|null} null se inválido
 */
export function parseDecimal(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : null;
    }

    const normalized = String(value).trim().replace(/\s/g, '').replace(',', '.');

    if (!/^-?\d+(\.\d+)?$/.test(normalized)) {
        return null;
    }

    const amount = Number.parseFloat(normalized);

    return Number.isFinite(amount) ? amount : null;
}

/**
 * @param {string|number|null|undefined} value
 * @returns {string} ex.: `R$ 1.234,56`
 */
export function formatMoney(value) {
    const amount = parseDecimal(value);

    return moneyFormatter.format(amount ?? 0);
}

/**
 * @param {string|null|undefined} isoDate YYYY-MM-DD
 * @returns {string} ex.: `01/09/2026`
 */
export function formatDate(isoDate) {
    if (!isoDate) {
        return '—';
    }

    const match = String(isoDate)
        .slice(0, 10)
        .match(/^(\d{4})-(\d{2})-(\d{2})$/);

    if (!match) {
        return String(isoDate);
    }

    const [, year, month, day] = match;

    return `${day}/${month}/${year}`;
}

/**
 * Labels de eixo/tooltip: `2026-09` → `set/2026`; `2026-09-01` → `01/09`.
 * @param {string|null|undefined} period YYYY-MM or YYYY-MM-DD
 * @returns {string}
 */
export function formatPeriod(period) {
    if (!period) {
        return '—';
    }

    const raw = String(period);

    if (/^\d{4}-\d{2}$/.test(raw)) {
        const [year, month] = raw.split('-').map(Number);
        const short = new Intl.DateTimeFormat('pt-BR', { month: 'short' })
            .format(new Date(year, month - 1, 1))
            .replace(/\./g, '')
            .trim()
            .toLowerCase();

        return `${short}/${year}`;
    }

    if (/^\d{4}-\d{2}-\d{2}/.test(raw)) {
        const [, month, day] = raw.slice(0, 10).split('-');

        return `${day}/${month}`;
    }

    return String(period);
}

/**
 * Exibição tabular com sinal.
 * @param {'credit'|'debit'|string} type
 * @param {string|number|null|undefined} amount
 * @returns {string}
 */
export function signedMoney(type, amount) {
    const formatted = formatMoney(amount);

    if (type === 'debit') {
        return `−${formatted}`;
    }

    if (type === 'credit') {
        return `+${formatted}`;
    }

    return formatted;
}

/**
 * Relative time pt-BR — PLAN_CARTOES_EMPRESTIMOS §6.6.
 * @param {string|null|undefined} iso
 * @returns {string}
 */
export function formatRelativeTime(iso) {
    if (!iso) {
        return '';
    }

    const then = new Date(iso).getTime();

    if (!Number.isFinite(then)) {
        return '';
    }

    const diffSec = Math.round((then - Date.now()) / 1000);
    const rtf = new Intl.RelativeTimeFormat('pt-BR', { numeric: 'auto' });
    const abs = Math.abs(diffSec);

    if (abs < 60) {
        return rtf.format(diffSec, 'second');
    }

    const diffMin = Math.round(diffSec / 60);
    if (Math.abs(diffMin) < 60) {
        return rtf.format(diffMin, 'minute');
    }

    const diffHour = Math.round(diffMin / 60);
    if (Math.abs(diffHour) < 24) {
        return rtf.format(diffHour, 'hour');
    }

    const diffDay = Math.round(diffHour / 24);
    if (Math.abs(diffDay) < 30) {
        return rtf.format(diffDay, 'day');
    }

    const diffMonth = Math.round(diffDay / 30);
    if (Math.abs(diffMonth) < 12) {
        return rtf.format(diffMonth, 'month');
    }

    return rtf.format(Math.round(diffMonth / 12), 'year');
}

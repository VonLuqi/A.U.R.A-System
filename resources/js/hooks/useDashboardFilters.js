import { useCallback, useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import {
    PERIOD_PRESET_IDS,
    PERIOD_PRESETS,
    currentMonthRange,
    normalizePeriodPreset,
    resolveGroupBy,
} from '../lib/dates';

const DEFAULT_PER_PAGE = 20;
const DEFAULT_SORT = 'occurred_on';
const DEFAULT_DIRECTION = 'desc';
const Q_DEBOUNCE_MS = 300;

/**
 * @typedef {import('../lib/dates').PeriodPresetId} PeriodPresetId
 *
 * @typedef {object} DashboardFilters
 * @property {string} from ISO YYYY-MM-DD
 * @property {string} to ISO YYYY-MM-DD
 * @property {PeriodPresetId} preset
 * @property {''|'credit'|'debit'} type
 * @property {number|''} category_id
 * @property {string} q
 * @property {'day'|'month'} group_by
 * @property {number} page
 * @property {number} per_page
 * @property {'occurred_on'|'amount'|'created_at'|string} sort
 * @property {'asc'|'desc'} direction
 */

/**
 * Detecta preset ativo a partir de from/to (quando a URL não traz `preset`).
 * @param {string} from
 * @param {string} to
 * @returns {PeriodPresetId}
 */
function detectPeriodPreset(from, to) {
    for (const id of Object.keys(PERIOD_PRESETS)) {
        const range = PERIOD_PRESETS[id]();

        if (range.from === from && range.to === to) {
            return /** @type {PeriodPresetId} */ (id);
        }
    }

    return PERIOD_PRESET_IDS.custom;
}

/**
 * Lê filtros da URL (contrato §6.2).
 *
 * Exemplos:
 * - `?preset=last_30` → recalcula últimos 30 dias + grava from/to implicitamente no estado
 * - `?from=2026-08-01&to=2026-08-31&preset=custom` → intervalo livre
 * - (vazio) → mês corrente + `preset=current_month`
 *
 * @param {URLSearchParams} params
 * @returns {DashboardFilters}
 */
function parseFiltersFromParams(params) {
    const defaults = currentMonthRange();
    const presetParam = normalizePeriodPreset(params.get('preset'));
    const fromParam = params.get('from') || '';
    const toParam = params.get('to') || '';

    /** @type {string} */
    let from;
    /** @type {string} */
    let to;
    /** @type {PeriodPresetId} */
    let preset;

    if (presetParam && presetParam !== PERIOD_PRESET_IDS.custom && PERIOD_PRESETS[presetParam]) {
        // Named presets: always recompute relative to "today" (shareable + fresh).
        const range = PERIOD_PRESETS[presetParam]();
        from = range.from;
        to = range.to;
        preset = presetParam;
    } else if (fromParam && toParam) {
        from = fromParam;
        to = toParam;
        preset = presetParam === PERIOD_PRESET_IDS.custom
            ? PERIOD_PRESET_IDS.custom
            : detectPeriodPreset(from, to);
    } else {
        from = defaults.from;
        to = defaults.to;
        preset = PERIOD_PRESET_IDS.current_month;
    }

    const typeRaw = params.get('type') || '';
    const type = typeRaw === 'credit' || typeRaw === 'debit' ? typeRaw : '';
    const categoryRaw = params.get('category_id') || '';
    const category_id = categoryRaw && /^\d+$/.test(categoryRaw) ? Number(categoryRaw) : '';
    const q = params.get('q') || '';
    const pageRaw = Number(params.get('page') || 1);
    const page = Number.isFinite(pageRaw) && pageRaw >= 1 ? pageRaw : 1;
    const sortRaw = params.get('sort') || DEFAULT_SORT;
    const sort = ['occurred_on', 'amount', 'created_at'].includes(sortRaw)
        ? sortRaw
        : DEFAULT_SORT;
    const directionRaw = params.get('direction') || DEFAULT_DIRECTION;
    const direction = directionRaw === 'asc' ? 'asc' : 'desc';

    return {
        from,
        to,
        preset,
        type,
        category_id,
        q,
        group_by: resolveGroupBy(from, to),
        page,
        per_page: DEFAULT_PER_PAGE,
        sort,
        direction,
    };
}

/**
 * Serializa filtros → URLSearchParams (sempre inclui from, to, preset).
 * @param {DashboardFilters} filters
 */
function filtersToSearchParams(filters) {
    const params = new URLSearchParams();

    if (filters.from) {
        params.set('from', filters.from);
    }

    if (filters.to) {
        params.set('to', filters.to);
    }

    if (filters.preset) {
        params.set('preset', filters.preset);
    }

    if (filters.type) {
        params.set('type', filters.type);
    }

    if (filters.category_id !== '' && filters.category_id != null) {
        params.set('category_id', String(filters.category_id));
    }

    if (filters.q) {
        params.set('q', filters.q);
    }

    if (filters.page > 1) {
        params.set('page', String(filters.page));
    }

    if (filters.sort !== DEFAULT_SORT) {
        params.set('sort', filters.sort);
    }

    if (filters.direction !== DEFAULT_DIRECTION) {
        params.set('direction', filters.direction);
    }

    return params;
}

/**
 * Dashboard filters — Etapa D §4.2 / PLAN_EXPANSAO §6.2.
 *
 * ## Contrato URL / API
 *
 * | Param | Valores | Notas |
 * | --- | --- | --- |
 * | `from` / `to` | `YYYY-MM-DD` | Sempre gravados na URL; backend exige o par |
 * | `preset` | `current_month` \| `last_30` \| `last_90` \| `custom` | Named recomputa bounds; `custom` exige from/to |
 * | `group_by` | `day` \| `month` | Só enviado à API analytics (não na URL); ≤45 dias → `day` |
 * | `type` | `credit` \| `debit` | Opcional |
 * | `category_id` | int | Opcional |
 * | `q` | string ≤120 | Debounce 300ms em `apiFilters` |
 * | `page` / `sort` / `direction` | pagination | Só listagem |
 *
 * Exemplo custom: `?from=2026-08-01&to=2026-08-31&preset=custom`
 *
 * Sync URL via `searchParams`; `apiFilters` inclui `preset` + `group_by` para o backend.
 *
 * @returns {{
 *   filters: DashboardFilters,
 *   apiFilters: DashboardFilters,
 *   periodPreset: PeriodPresetId,
 *   setFilters: (patch: Partial<DashboardFilters>) => void,
 *   setPeriodPreset: (presetId: Exclude<PeriodPresetId, 'custom'>) => void,
 *   setCustomRange: (range: { from: string, to: string }) => void,
 *   setPage: (page: number) => void,
 * }}
 */
export function useDashboardFilters() {
    const [searchParams, setSearchParams] = useSearchParams();

    const filters = useMemo(
        () => parseFiltersFromParams(searchParams),
        [searchParams],
    );

    const [debouncedQ, setDebouncedQ] = useState(filters.q);

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setDebouncedQ(filters.q);
        }, Q_DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
    }, [filters.q]);

    const apiFilters = useMemo(
        () => ({
            ...filters,
            q: debouncedQ,
            group_by: resolveGroupBy(filters.from, filters.to),
        }),
        [filters, debouncedQ],
    );

    const periodPreset = filters.preset;

    const replaceFilters = useCallback(
        (next) => {
            const from = next.from ?? filters.from;
            const to = next.to ?? filters.to;
            const preset = next.preset
                ?? (next.from || next.to ? detectPeriodPreset(from, to) : filters.preset);

            const merged = {
                ...filters,
                ...next,
                from,
                to,
                preset,
                group_by: resolveGroupBy(from, to),
            };

            setSearchParams(filtersToSearchParams(merged), { replace: true });
        },
        [filters, setSearchParams],
    );

    const setFilters = useCallback(
        (patch) => {
            const next = { ...patch };

            if (!('page' in patch)) {
                next.page = 1;
            }

            replaceFilters(next);
        },
        [replaceFilters],
    );

    const setPeriodPreset = useCallback(
        (presetId) => {
            const range = PERIOD_PRESETS[presetId]?.();

            if (!range) {
                return;
            }

            setFilters({
                from: range.from,
                to: range.to,
                preset: presetId,
            });
        },
        [setFilters],
    );

    const setPage = useCallback(
        (page) => {
            replaceFilters({ page: Math.max(1, Number(page) || 1) });
        },
        [replaceFilters],
    );

    /**
     * Intervalo livre → URL `?from=…&to=…&preset=custom`.
     * @param {{ from: string, to: string }} range
     */
    const setCustomRange = useCallback(
        ({ from, to }) => {
            if (!from || !to) {
                return;
            }

            setFilters({ from, to, preset: PERIOD_PRESET_IDS.custom });
        },
        [setFilters],
    );

    return {
        filters,
        apiFilters,
        periodPreset,
        setFilters,
        setPeriodPreset,
        setCustomRange,
        setPage,
    };
}

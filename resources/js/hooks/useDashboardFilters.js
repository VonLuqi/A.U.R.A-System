import { useCallback, useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useAuth } from './useAuth';
import {
    PERIOD_PRESET_IDS,
    PERIOD_PRESETS,
    currentMonthRange,
    isCyclePreset,
    normalizePeriodPreset,
    resolveCycleRange,
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
 * @property {string} from ISO YYYY-MM-DD (vazio com preset=all)
 * @property {string} to ISO YYYY-MM-DD (vazio com preset=all)
 * @property {PeriodPresetId} preset
 * @property {number} cycle_offset
 * @property {''|'credit'|'debit'} type
 * @property {number|''} category_id
 * @property {number[]} credit_card_ids
 * @property {boolean} include_uncarded
 * @property {number|''} debtor_id
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
        if (id === PERIOD_PRESET_IDS.all) {
            continue;
        }

        const range = PERIOD_PRESETS[id]();

        if (range.from === from && range.to === to) {
            return /** @type {PeriodPresetId} */ (id);
        }
    }

    return PERIOD_PRESET_IDS.custom;
}

/**
 * @param {URLSearchParams} params
 * @returns {DashboardFilters}
 */
function parseFiltersFromParams(params) {
    const defaults = currentMonthRange();
    const presetParam = normalizePeriodPreset(params.get('preset'));
    const fromParam = params.get('from') || '';
    const toParam = params.get('to') || '';
    const offsetRaw = Number(params.get('cycle_offset') || 0);
    const cycle_offset = Number.isFinite(offsetRaw)
        ? Math.max(-120, Math.min(120, offsetRaw))
        : 0;

    /** @type {string} */
    let from;
    /** @type {string} */
    let to;
    /** @type {PeriodPresetId} */
    let preset;

    if (presetParam === PERIOD_PRESET_IDS.all) {
        from = '';
        to = '';
        preset = PERIOD_PRESET_IDS.all;
    } else if (isCyclePreset(presetParam)) {
        from = fromParam;
        to = toParam;
        preset = /** @type {PeriodPresetId} */ (presetParam);
    } else if (presetParam && presetParam !== PERIOD_PRESET_IDS.custom && PERIOD_PRESETS[presetParam]) {
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
    const creditCardsRaw = params.get('credit_card_ids') || params.get('credit_card_id') || '';
    const credit_card_ids = creditCardsRaw
        ? creditCardsRaw
            .split(',')
            .map((part) => part.trim())
            .filter((part) => /^\d+$/.test(part))
            .map((part) => Number(part))
        : [];
    const include_uncarded = params.get('include_uncarded') === '1'
        || params.get('include_uncarded') === 'true';
    const debtorRaw = params.get('debtor_id') || '';
    const debtor_id = debtorRaw && /^\d+$/.test(debtorRaw) ? Number(debtorRaw) : '';
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
        cycle_offset: isCyclePreset(preset) ? cycle_offset : 0,
        type,
        category_id,
        credit_card_ids,
        include_uncarded: credit_card_ids.length > 0 ? include_uncarded : false,
        debtor_id,
        q,
        group_by: resolveGroupBy(from, to),
        page,
        per_page: DEFAULT_PER_PAGE,
        sort,
        direction,
    };
}

/**
 * @param {DashboardFilters} filters
 */
function filtersToSearchParams(filters) {
    const params = new URLSearchParams();

    if (filters.preset !== PERIOD_PRESET_IDS.all) {
        if (filters.from) {
            params.set('from', filters.from);
        }

        if (filters.to) {
            params.set('to', filters.to);
        }
    }

    if (filters.preset) {
        params.set('preset', filters.preset);
    }

    if (isCyclePreset(filters.preset) && filters.cycle_offset) {
        params.set('cycle_offset', String(filters.cycle_offset));
    }

    if (filters.type) {
        params.set('type', filters.type);
    }

    if (filters.category_id !== '' && filters.category_id != null) {
        params.set('category_id', String(filters.category_id));
    }

    if (Array.isArray(filters.credit_card_ids) && filters.credit_card_ids.length > 0) {
        params.set('credit_card_ids', filters.credit_card_ids.join(','));
    }

    if (filters.include_uncarded && Array.isArray(filters.credit_card_ids) && filters.credit_card_ids.length > 0) {
        params.set('include_uncarded', '1');
    }

    if (filters.debtor_id !== '' && filters.debtor_id != null) {
        params.set('debtor_id', String(filters.debtor_id));
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
 * @param {DashboardFilters} filters
 * @param {{ user?: object|null }} ctx
 * @returns {DashboardFilters}
 */
function withResolvedCycleBounds(filters, { user }) {
    if (!isCyclePreset(filters.preset)) {
        return filters;
    }

    const range = resolveCycleRange({
        preset: filters.preset,
        type: filters.type,
        cycleOffset: filters.cycle_offset ?? 0,
        user,
    });

    if (!range) {
        return filters;
    }

    return {
        ...filters,
        from: range.from,
        to: range.to,
        group_by: resolveGroupBy(range.from, range.to),
    };
}

/**
 * Dashboard filters — Etapa D §4.2 / PLAN_EXPANSAO §6.2 + Meu ciclo.
 */
export function useDashboardFilters() {
    const { user } = useAuth();
    const [searchParams, setSearchParams] = useSearchParams();

    const filters = useMemo(() => {
        const parsed = parseFiltersFromParams(searchParams);

        return withResolvedCycleBounds(parsed, { user });
    }, [searchParams, user]);

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
            let merged = {
                ...filters,
                ...next,
            };

            const preset = merged.preset
                ?? (merged.from || merged.to
                    ? detectPeriodPreset(merged.from ?? filters.from, merged.to ?? filters.to)
                    : filters.preset);

            merged.preset = preset;

            if (preset === PERIOD_PRESET_IDS.all) {
                merged.from = '';
                merged.to = '';
                merged.cycle_offset = 0;
            } else if (isCyclePreset(preset)) {
                if (!('cycle_offset' in next) && !isCyclePreset(filters.preset)) {
                    merged.cycle_offset = 0;
                }
                merged = withResolvedCycleBounds(merged, { user });
            } else {
                merged.cycle_offset = 0;
                if (!('from' in next) && !('to' in next) && PERIOD_PRESETS[preset]) {
                    const range = PERIOD_PRESETS[preset]();
                    merged.from = range.from;
                    merged.to = range.to;
                } else {
                    merged.from = next.from ?? filters.from;
                    merged.to = next.to ?? filters.to;
                }
            }

            if (!Array.isArray(merged.credit_card_ids) || merged.credit_card_ids.length === 0) {
                merged.credit_card_ids = [];
                merged.include_uncarded = false;
            }

            merged.group_by = resolveGroupBy(merged.from, merged.to);

            setSearchParams(filtersToSearchParams(merged), { replace: true });
        },
        [filters, setSearchParams, user],
    );

    const setFilters = useCallback(
        (patch) => {
            const next = { ...patch };

            if (!('page' in patch)) {
                next.page = 1;
            }

            // Changing type while on Meu ciclo recalculates bounds.
            if (
                isCyclePreset(filters.preset)
                && ('type' in patch)
                && !('preset' in patch)
            ) {
                next.preset = filters.preset;
            }

            replaceFilters(next);
        },
        [replaceFilters, filters.preset],
    );

    const setPeriodPreset = useCallback(
        (presetId) => {
            if (isCyclePreset(presetId)) {
                setFilters({
                    preset: presetId,
                    cycle_offset: 0,
                });
                return;
            }

            const range = PERIOD_PRESETS[presetId]?.();

            if (!range) {
                return;
            }

            setFilters({
                from: range.from,
                to: range.to,
                preset: presetId,
                cycle_offset: 0,
            });
        },
        [setFilters],
    );

    const setCycleOffset = useCallback(
        (offset) => {
            if (!isCyclePreset(filters.preset)) {
                return;
            }

            setFilters({
                preset: filters.preset,
                cycle_offset: Math.max(-120, Math.min(120, Number(offset) || 0)),
            });
        },
        [filters.preset, setFilters],
    );

    const setPage = useCallback(
        (page) => {
            replaceFilters({ page: Math.max(1, Number(page) || 1) });
        },
        [replaceFilters],
    );

    /**
     * @param {{ from: string, to: string }} range
     */
    const setCustomRange = useCallback(
        ({ from, to }) => {
            if (!from || !to) {
                return;
            }

            setFilters({ from, to, preset: PERIOD_PRESET_IDS.custom, cycle_offset: 0 });
        },
        [setFilters],
    );

    return {
        filters,
        apiFilters,
        periodPreset,
        setFilters,
        setPeriodPreset,
        setCycleOffset,
        setCustomRange,
        setPage,
    };
}

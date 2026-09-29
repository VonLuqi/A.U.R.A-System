import { useCallback, useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import {
    PERIOD_PRESET_IDS,
    PERIOD_PRESETS,
    currentMonthRange,
    resolveGroupBy,
} from '../lib/dates';

const DEFAULT_PER_PAGE = 20;
const DEFAULT_SORT = 'occurred_on';
const DEFAULT_DIRECTION = 'desc';
const Q_DEBOUNCE_MS = 300;

/**
 * @param {URLSearchParams} params
 */
function parseFiltersFromParams(params) {
    const defaults = currentMonthRange();
    const from = params.get('from') || defaults.from;
    const to = params.get('to') || defaults.to;
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
 * @param {ReturnType<typeof parseFiltersFromParams>} filters
 */
function filtersToSearchParams(filters) {
    const params = new URLSearchParams();

    if (filters.from) {
        params.set('from', filters.from);
    }

    if (filters.to) {
        params.set('to', filters.to);
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
 * Detecta preset ativo a partir de from/to (aproximação).
 * @param {string} from
 * @param {string} to
 */
function detectPeriodPreset(from, to) {
    for (const id of Object.values(PERIOD_PRESET_IDS)) {
        const range = PERIOD_PRESETS[id]();

        if (range.from === from && range.to === to) {
            return id;
        }
    }

    return 'custom';
}

/**
 * Dashboard filters — Etapa D §4.2.
 * Sync URL via searchParams; `apiFilters` usa `q` com debounce 300ms.
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

    const periodPreset = detectPeriodPreset(filters.from, filters.to);

    const replaceFilters = useCallback(
        (next) => {
            const merged = {
                ...filters,
                ...next,
                group_by: resolveGroupBy(
                    next.from ?? filters.from,
                    next.to ?? filters.to,
                ),
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

            setFilters({ from: range.from, to: range.to });
        },
        [setFilters],
    );

    const setPage = useCallback(
        (page) => {
            replaceFilters({ page: Math.max(1, Number(page) || 1) });
        },
        [replaceFilters],
    );

    const setCustomRange = useCallback(
        ({ from, to }) => {
            if (!from || !to) {
                return;
            }

            setFilters({ from, to });
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

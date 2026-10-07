import { describe, expect, it } from 'vitest';
import { cleanApiParams, toAnalyticsParams, toTransactionsParams } from './apiParams.js';
import {
    cycleDayForType,
    cycleDayRange,
    dateRangeLimitMessage,
    daysBetween,
    formatRangeLabel,
    isWithinDateRangeLimit,
    maxDateRangeDaysFor,
    normalizePeriodPreset,
    parseIsoDate,
    resolveGroupBy,
} from './dates.js';

describe('dates §6.2', () => {
    it('normalizes period presets', () => {
        expect(normalizePeriodPreset('custom')).toBe('custom');
        expect(normalizePeriodPreset('LAST_30')).toBe('last_30');
        expect(normalizePeriodPreset('all')).toBe('all');
        expect(normalizePeriodPreset('my_cycle')).toBe('my_cycle');
        expect(normalizePeriodPreset('card_cycle')).toBe('card_cycle');
        expect(normalizePeriodPreset('ytd')).toBeNull();
        expect(normalizePeriodPreset('')).toBeNull();
    });

    it('computes inclusive cycle day ranges', () => {
        const now = new Date(2026, 9, 7); // 7 Oct 2026
        expect(cycleDayRange(6, 0, now)).toEqual({ from: '2026-10-06', to: '2026-11-06' });
        expect(cycleDayRange(6, -1, now)).toEqual({ from: '2026-09-06', to: '2026-10-06' });
        expect(cycleDayForType('credit', 6, 12)).toBe(12);
        expect(cycleDayForType('debit', 6, 12)).toBe(6);
    });

    it('resolveGroupBy uses inclusive 45-day threshold', () => {
        expect(daysBetween('2026-09-01', '2026-09-30')).toBe(30);
        expect(resolveGroupBy('2026-09-01', '2026-09-30')).toBe('day');
        expect(resolveGroupBy('2026-08-01', '2026-09-14')).toBe('day');
        expect(resolveGroupBy('2026-08-01', '2026-09-15')).toBe('month');
        expect(resolveGroupBy('', '')).toBe('month');
    });
});

describe('dates §8.3 limits', () => {
    it('reads max_date_range_days (0 = unlimited)', () => {
        expect(maxDateRangeDaysFor({ limits: { max_date_range_days: 90 } })).toBe(90);
        expect(maxDateRangeDaysFor({ limits: { max_date_range_days: 0 } })).toBeNull();
        expect(maxDateRangeDaysFor(null)).toBeNull();
    });

    it('blocks ranges beyond role limit', () => {
        expect(isWithinDateRangeLimit('2026-01-01', '2026-04-01', 90)).toBe(false);
        expect(isWithinDateRangeLimit('2026-01-01', '2026-03-31', 90)).toBe(true);
        expect(isWithinDateRangeLimit('2026-01-01', '2026-12-31', null)).toBe(true);
        expect(dateRangeLimitMessage(60, 91)).toContain('60 dias');
    });

    it('formats and parses ISO dates', () => {
        expect(formatRangeLabel('2026-08-01', '2026-08-31')).toBe('01/08/2026 – 31/08/2026');
        expect(formatRangeLabel('', '')).toBe('Todo o histórico');
        expect(parseIsoDate('2026-09-29')?.getDate()).toBe(29);
        expect(parseIsoDate('nope')).toBeNull();
    });
});

describe('apiParams §6.2', () => {
    it('forwards preset on analytics and transactions params', () => {
        expect(toAnalyticsParams({
            from: '2026-08-01',
            to: '2026-08-31',
            preset: 'custom',
            group_by: 'day',
            credit_card_id: 3,
            debtor_id: 7,
            q: '',
        })).toEqual({
            from: '2026-08-01',
            to: '2026-08-31',
            preset: 'custom',
            group_by: 'day',
            credit_card_id: 3,
            debtor_id: 7,
        });

        expect(toTransactionsParams({
            from: '2026-08-01',
            to: '2026-08-31',
            preset: 'custom',
            credit_card_id: 3,
            debtor_id: 7,
            page: 1,
        })).toEqual({
            from: '2026-08-01',
            to: '2026-08-31',
            preset: 'custom',
            credit_card_id: 3,
            debtor_id: 7,
            page: 1,
        });
    });

    it('sends preset=all without from/to', () => {
        expect(toAnalyticsParams({
            from: '',
            to: '',
            preset: 'all',
            group_by: 'month',
        })).toEqual({
            preset: 'all',
            group_by: 'month',
        });

        expect(toTransactionsParams({
            from: '',
            to: '',
            preset: 'all',
            page: 1,
        })).toEqual({
            preset: 'all',
            page: 1,
        });
    });

    it('cleanApiParams drops empty values', () => {
        expect(cleanApiParams({ a: '', b: null, c: 1 })).toEqual({ c: 1 });
    });
});

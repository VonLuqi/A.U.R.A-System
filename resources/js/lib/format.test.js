import { describe, expect, it } from 'vitest';
import { formatMoney } from './format.js';

/** Normaliza NBSP / espaço estreito do Intl para comparação estável. */
function normalizeMoney(value) {
    return String(value).replace(/[\u00a0\u202f]/g, ' ');
}

describe('formatMoney', () => {
    it('formata decimal da API em BRL pt-BR', () => {
        expect(normalizeMoney(formatMoney('1234.56'))).toBe('R$ 1.234,56');
        expect(normalizeMoney(formatMoney(0))).toBe('R$ 0,00');
        expect(normalizeMoney(formatMoney(null))).toBe('R$ 0,00');
    });
});

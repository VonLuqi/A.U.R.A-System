import { describe, expect, it } from 'vitest';
import { isAllowedStatementFile, MAX_STATEMENT_BYTES } from './validators.js';

function fakeFile(name, size) {
    return { name, size };
}

describe('isAllowedStatementFile', () => {
    it('aceita csv/ofx/qfx dentro do limite', () => {
        expect(isAllowedStatementFile(fakeFile('nubank.csv', 100))).toBe(true);
        expect(isAllowedStatementFile(fakeFile('extrato.ofx', 100))).toBe(true);
        expect(isAllowedStatementFile(fakeFile('extrato.qfx', 100))).toBe(true);
    });

    it('rejeita extensão inválida, vazio e >10MB', () => {
        expect(isAllowedStatementFile(fakeFile('malware.exe', 100))).toBe(false);
        expect(isAllowedStatementFile(fakeFile('doc.pdf', 100))).toBe(false);
        expect(isAllowedStatementFile(fakeFile('empty.csv', 0))).toBe(false);
        expect(isAllowedStatementFile(fakeFile('huge.csv', MAX_STATEMENT_BYTES + 1))).toBe(false);
    });
});

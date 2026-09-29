import { describe, expect, it } from 'vitest';
import {
    formatMismatchAction,
    statementKindOption,
} from './statementKinds.js';

describe('statementKinds §8.4', () => {
    it('maps checking and credit_card to API source/kind', () => {
        expect(statementKindOption('checking')).toMatchObject({
            source: 'nubank',
            statement_kind: 'checking',
        });
        expect(statementKindOption('credit_card')).toMatchObject({
            source: 'nubank_credit',
            statement_kind: 'credit_card',
        });
    });

    it('suggests switching kind on detector/parser 422', () => {
        expect(formatMismatchAction({ errorCode: 'invalid_statement' }, 'checking')).toMatchObject({
            nextKind: 'credit_card',
        });
        expect(formatMismatchAction({ errorCode: 'unsupported_format' }, 'credit_card')).toMatchObject({
            nextKind: 'checking',
        });
        expect(formatMismatchAction({ errorCode: 'other' }, 'checking')).toBeNull();
    });
});

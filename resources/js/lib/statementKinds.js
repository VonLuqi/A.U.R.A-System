/**
 * Upload statement kinds — PLAN_EXPANSAO §8.4.
 *
 * Maps UI selector → API `source` + `statement_kind`.
 */

/** @typedef {'checking'|'credit_card'} StatementKindId */

/**
 * @typedef {{
 *   id: StatementKindId,
 *   label: string,
 *   source: 'nubank'|'nubank_credit',
 *   statement_kind: StatementKindId,
 *   help: string,
 *   formats: string,
 *   sampleHref: string,
 *   sampleLabel: string,
 * }} StatementKindOption
 */

/** @type {StatementKindOption[]} */
export const STATEMENT_KIND_OPTIONS = [
    {
        id: 'checking',
        label: 'Conta corrente',
        source: 'nubank',
        statement_kind: 'checking',
        help: 'Extrato de conta Nubank em CSV (Data, Valor, Descrição) ou OFX/QFX.',
        formats: 'CSV · OFX · QFX · até 10 MB',
        sampleHref: '/samples/nubank-conta-exemplo.csv',
        sampleLabel: 'Baixar exemplo CSV (conta)',
    },
    {
        id: 'credit_card',
        label: 'Fatura cartão',
        source: 'nubank_credit',
        statement_kind: 'credit_card',
        help: 'Fatura do cartão Nubank em CSV (date, title, amount). OFX de cartão ainda não é suportado.',
        formats: 'CSV · até 10 MB',
        sampleHref: '/samples/nubank-fatura-exemplo.csv',
        sampleLabel: 'Baixar exemplo CSV (fatura)',
    },
];

/**
 * @param {StatementKindId|string} id
 * @returns {StatementKindOption}
 */
export function statementKindOption(id) {
    return (
        STATEMENT_KIND_OPTIONS.find((option) => option.id === id) ??
        STATEMENT_KIND_OPTIONS[0]
    );
}

/**
 * Sugestão acionável quando o detector/parser rejeita o arquivo (§8.4).
 *
 * @param {{ errorCode?: string }|null|undefined} error
 * @param {StatementKindId} currentKind
 * @returns {{
 *   nextKind: StatementKindId,
 *   label: string,
 *   hint: string,
 * }|null}
 */
export function formatMismatchAction(error, currentKind) {
    const code = error?.errorCode;

    if (code !== 'invalid_statement' && code !== 'unsupported_format') {
        return null;
    }

    if (currentKind === 'checking') {
        return {
            nextKind: 'credit_card',
            label: 'Usar Fatura cartão',
            hint: 'Se este arquivo é a fatura do cartão Nubank, troque o tipo e envie novamente.',
        };
    }

    return {
        nextKind: 'checking',
        label: 'Usar Conta corrente',
        hint: 'Se este arquivo é o extrato da conta, troque o tipo e envie novamente.',
    };
}

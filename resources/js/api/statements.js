/**
 * Statements API — Etapa D §5.1 / §3.5 / §5.6 / PLAN_EXPANSAO §8.4.
 *
 * | Método | Path | Resposta |
 * | --- | --- | --- |
 * | POST | `/api/statements/upload` | `201 { data: UploadSummary }` |
 * | GET | `/api/statements` | `{ data: StatementImport[], meta }` (opcional UI) |
 * | GET | `/api/statements/:id` | `{ data: StatementImport }` |
 *
 * Upload FormData: `file`, `source` (`nubank`|`nubank_credit`|`other`),
 * `statement_kind` (`checking`|`credit_card`).
 *
 * Erros upload: 422 validation (`errors.file|source|statement_kind`) /
 * parse (`error_code`, `import_id?`); 413 tamanho; 429 throttle; 401 interceptor.
 *
 * Segurança client (§5.6): não logar conteúdo do arquivo; UI não renderiza
 * `stored_path` / checksum completo (só campos públicos do summary).
 *
 * @typedef {{
 *   import_id?: number,
 *   rows_total?: number,
 *   rows_imported?: number,
 *   rows_skipped?: number,
 *   rows_failed?: number,
 *   period_start?: string|null,
 *   period_end?: string|null,
 *   format?: string,
 *   source?: string,
 *   [key: string]: unknown,
 * }} UploadSummary
 *
 * @typedef {{
 *   id: number,
 *   original_filename?: string,
 *   status?: string,
 *   [key: string]: unknown,
 * }} StatementImport
 *
 * @typedef {{
 *   source?: string,
 *   statement_kind?: 'checking'|'credit_card'|string,
 *   credit_card_id?: number|string|null,
 * }} UploadStatementOptions
 */
import { ensureCsrf } from './auth';
import api from './client';
import { getErrorMessage } from '../lib/errors';

export class StatementUploadError extends Error {
    /**
     * @param {string} message
     * @param {{
     *   status?: number,
     *   errorCode?: string,
     *   importId?: number,
     *   format?: string,
     *   source?: string,
     *   fieldErrors?: Record<string, string[]>,
     * }} [meta]
     */
    constructor(message, { status, errorCode, importId, format, source, fieldErrors } = {}) {
        super(message);
        this.name = 'StatementUploadError';
        this.status = status;
        this.errorCode = errorCode;
        this.importId = importId;
        this.format = format;
        this.source = source;
        this.fieldErrors = fieldErrors ?? null;
    }
}

/**
 * @param {unknown} error
 * @returns {StatementUploadError}
 */
export function normalizeUploadError(error) {
    const axiosError = /** @type {{ response?: { status?: number, data?: Record<string, unknown> } }} */ (
        error
    );
    const status = axiosError.response?.status;
    const data = axiosError.response?.data ?? {};

    if (status === 422) {
        const fieldErrors = /** @type {Record<string, string[]>|undefined} */ (data.errors);

        const firstFieldMessage =
            fieldErrors?.file?.[0] ||
            fieldErrors?.statement_kind?.[0] ||
            fieldErrors?.source?.[0] ||
            null;

        const errorCode = typeof data.error_code === 'string' ? data.error_code : undefined;
        const importId = typeof data.import_id === 'number' ? data.import_id : undefined;
        const format = typeof data.format === 'string' ? data.format : undefined;
        const source = typeof data.source === 'string' ? data.source : undefined;

        const message =
            firstFieldMessage ||
            (typeof data.message === 'string' && data.message
                ? data.message
                : 'Não foi possível ler o extrato.');

        return new StatementUploadError(message, {
            status: 422,
            errorCode,
            importId,
            format,
            source,
            fieldErrors: fieldErrors ?? null,
        });
    }

    if (status === 413) {
        return new StatementUploadError('Arquivo muito grande.', { status: 413 });
    }

    if (status === 429) {
        return new StatementUploadError(getErrorMessage(error), { status: 429 });
    }

    // 401 e demais: mensagem genérica; interceptor já trata redirect em 401.
    return new StatementUploadError(getErrorMessage(error), { status });
}

/**
 * @param {File} file
 * @param {UploadStatementOptions} [options]
 * @returns {Promise<UploadSummary>}
 */
export async function uploadStatement(
    file,
    { source = 'nubank', statement_kind = 'checking', credit_card_id = null } = {},
) {
    await ensureCsrf();

    const formData = new FormData();
    formData.append('file', file);

    if (source) {
        formData.append('source', source);
    }

    if (statement_kind) {
        formData.append('statement_kind', statement_kind);
    }

    if (credit_card_id != null && credit_card_id !== '') {
        formData.append('credit_card_id', String(credit_card_id));
    }

    // Não setar Content-Type — o browser define multipart boundary.
    try {
        const { data, status } = await api.post('/api/statements/upload', formData);

        if (status === 201 || (status >= 200 && status < 300)) {
            return data.data;
        }

        return data.data;
    } catch (error) {
        throw normalizeUploadError(error);
    }
}

/**
 * @param {Record<string, string|number|undefined>} [params]
 * @returns {Promise<{ data: StatementImport[], meta: { current_page: number, per_page: number, total: number, last_page: number } }>}
 */
export async function listStatements(params = {}) {
    const { data } = await api.get('/api/statements', { params });

    return data;
}

/**
 * @param {number|string} id
 * @returns {Promise<StatementImport>}
 */
export async function getStatement(id) {
    const { data } = await api.get(`/api/statements/${id}`);

    return data.data;
}

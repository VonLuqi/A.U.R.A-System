/**
 * Validação superficial de extrato (Etapa D §3.4).
 * NÃO parseia CSV/OFX no browser — só extensão + tamanho; parse é 100% backend.
 *
 * Mensagens alinhadas a `UploadStatementRequest` (Etapa C).
 */

export const ALLOWED_EXTENSIONS = ['csv', 'ofx', 'qfx'];

/** 10 MB — espelha `max:10240` KB do FormRequest. */
export const MAX_STATEMENT_BYTES = 10 * 1024 * 1024;

export const STATEMENT_VALIDATION_MESSAGES = {
    required: 'Envie um arquivo de extrato.',
    invalidFile: 'O upload deve ser um arquivo válido.',
    maxSize: 'O arquivo deve ter no máximo 10 MB.',
    unsupportedFormat: 'Formato não suportado. Use CSV ou OFX (também .qfx).',
};

/**
 * @param {string} filename
 * @returns {string}
 */
export function getExtension(filename) {
    const parts = String(filename).toLowerCase().split('.');

    return parts.length > 1 ? parts.pop() : '';
}

/**
 * @param {File|null|undefined} file
 * @returns {{ ok: true } | { ok: false, message: string, code: string }}
 */
export function validateStatementFile(file) {
    if (!file) {
        return {
            ok: false,
            message: STATEMENT_VALIDATION_MESSAGES.required,
            code: 'required',
        };
    }

    if (typeof file.size !== 'number' || typeof file.name !== 'string') {
        return {
            ok: false,
            message: STATEMENT_VALIDATION_MESSAGES.invalidFile,
            code: 'invalid',
        };
    }

    const ext = getExtension(file.name);

    if (!ALLOWED_EXTENSIONS.includes(ext)) {
        return {
            ok: false,
            message: STATEMENT_VALIDATION_MESSAGES.unsupportedFormat,
            code: 'unsupported_format',
        };
    }

    if (file.size <= 0) {
        return {
            ok: false,
            message: STATEMENT_VALIDATION_MESSAGES.invalidFile,
            code: 'empty',
        };
    }

    if (file.size > MAX_STATEMENT_BYTES) {
        return {
            ok: false,
            message: STATEMENT_VALIDATION_MESSAGES.maxSize,
            code: 'max_size',
        };
    }

    return { ok: true };
}

/**
 * @param {File} file
 * @returns {boolean}
 */
export function isAllowedStatementFile(file) {
    return validateStatementFile(file).ok;
}

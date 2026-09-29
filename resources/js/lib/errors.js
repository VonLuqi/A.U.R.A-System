/**
 * Tratamento de erros HTTP — Etapa D §5.2.
 *
 * | Status  | Comportamento UI                                      | Onde |
 * | ------- | ----------------------------------------------------- | ---- |
 * | `401`   | Logout client + `/login`                              | `api/client` interceptor + AuthContext |
 * | `419`   | Re-CSRF + retry 1×                                    | `api/client` interceptor |
 * | `422`   | Inline field errors e/ou toast com `message`          | `getValidationErrors` + callers |
 * | `429`   | Toast (+ disable temporário no caller se fizer sentido) | `api/client` interceptor |
 * | `404`   | Toast / empty (ex.: statement show)                   | caller / `getErrorMessage` |
 * | `500`   | Toast genérico PT-BR                                  | `api/client` interceptor |
 * | Network | Toast “Falha de conexão. Verifique a rede.”           | `api/client` interceptor |
 *
 * Prioridade de mensagem: `response.data.message` → fallback por status → genérico.
 */

export const GENERIC_ERROR_MESSAGE = 'Não foi possível processar a solicitação.';
export const NETWORK_ERROR_MESSAGE = 'Falha de conexão. Verifique a rede.';
export const SESSION_EXPIRED_MESSAGE = 'Sessão expirada. Tente novamente.';
export const VALIDATION_ERROR_MESSAGE = 'Dados inválidos.';
export const NOT_FOUND_MESSAGE = 'Recurso não encontrado.';
export const RATE_LIMIT_MESSAGE = 'Muitas tentativas. Aguarde e tente novamente.';

/**
 * @param {unknown} error
 * @returns {boolean}
 */
export function isCanceledError(error) {
    if (!error || typeof error !== 'object') {
        return false;
    }

    const err = /** @type {{ code?: string, name?: string }} */ (error);

    return (
        err.code === 'ERR_CANCELED' ||
        err.name === 'CanceledError' ||
        err.name === 'AbortError'
    );
}

/**
 * @param {unknown} error
 * @returns {number|null}
 */
export function getHttpStatus(error) {
    if (!error || typeof error !== 'object') {
        return null;
    }

    const status = /** @type {{ response?: { status?: number }, status?: number }} */ (error)
        .response?.status;

    if (typeof status === 'number') {
        return status;
    }

    if (typeof /** @type {{ status?: number }} */ (error).status === 'number') {
        return /** @type {{ status: number }} */ (error).status;
    }

    return null;
}

/**
 * Mensagem PT-BR para UI (toast / inline).
 * Prioriza `response.data.message`; depois fallbacks por status.
 *
 * @param {unknown} error
 * @returns {string}
 */
export function getErrorMessage(error) {
    if (!error || typeof error !== 'object') {
        return GENERIC_ERROR_MESSAGE;
    }

    if (isCanceledError(error)) {
        return GENERIC_ERROR_MESSAGE;
    }

    // AuthError / StatementUploadError (e similares) com message já amigável.
    if (
        typeof /** @type {{ message?: string }} */ (error).message === 'string' &&
        /** @type {{ message: string }} */ (error).message &&
        (/** @type {{ name?: string }} */ (error).name === 'AuthError' ||
            /** @type {{ name?: string }} */ (error).name === 'StatementUploadError')
    ) {
        return /** @type {{ message: string }} */ (error).message;
    }

    const axiosError = /** @type {{
     *   response?: { status?: number, data?: { message?: string } },
     *   message?: string,
     * }} */ (error);

    const status = axiosError.response?.status;
    const apiMessage =
        typeof axiosError.response?.data?.message === 'string' &&
        axiosError.response.data.message.trim()
            ? axiosError.response.data.message.trim()
            : null;

    if (!axiosError.response) {
        return NETWORK_ERROR_MESSAGE;
    }

    if (status === 401) {
        return apiMessage || 'Credenciais inválidas.';
    }

    if (status === 419) {
        return SESSION_EXPIRED_MESSAGE;
    }

    if (status === 404) {
        return apiMessage || NOT_FOUND_MESSAGE;
    }

    if (status === 422) {
        return apiMessage || VALIDATION_ERROR_MESSAGE;
    }

    if (status === 429) {
        return apiMessage || RATE_LIMIT_MESSAGE;
    }

    if (status === 413) {
        return apiMessage || 'Arquivo muito grande.';
    }

    if (status === 500 || status === 503) {
        return GENERIC_ERROR_MESSAGE;
    }

    return apiMessage || GENERIC_ERROR_MESSAGE;
}

/**
 * Erros de validação Laravel (`422` → `response.data.errors`).
 * Use no formulário para inline field errors.
 *
 * @param {unknown} error
 * @returns {Record<string, string[]>|null}
 */
export function getValidationErrors(error) {
    if (!error || typeof error !== 'object') {
        return null;
    }

    // StatementUploadError já normaliza fieldErrors.
    const uploadFieldErrors = /** @type {{ fieldErrors?: Record<string, string[]>|null }} */ (
        error
    ).fieldErrors;

    if (uploadFieldErrors && typeof uploadFieldErrors === 'object') {
        return uploadFieldErrors;
    }

    const response = /** @type {{ response?: { status?: number, data?: { errors?: Record<string, string[]> } } }} */ (
        error
    ).response;

    if (response?.status !== 422) {
        return null;
    }

    const errors = response.data?.errors;

    if (!errors || typeof errors !== 'object') {
        return null;
    }

    return errors;
}

/**
 * Alias §5.2 — expor `response.data.errors` ao formulário.
 * @param {unknown} error
 * @returns {Record<string, string[]>|null}
 */
export function getFieldErrors(error) {
    return getValidationErrors(error);
}

/**
 * Primeira mensagem de um campo (ou qualquer campo) em `422`.
 * @param {unknown} error
 * @param {string} [field]
 * @returns {string|null}
 */
export function getFirstFieldError(error, field) {
    const errors = getValidationErrors(error);

    if (!errors) {
        return null;
    }

    if (field && Array.isArray(errors[field]) && errors[field][0]) {
        return errors[field][0];
    }

    for (const messages of Object.values(errors)) {
        if (Array.isArray(messages) && messages[0]) {
            return messages[0];
        }
    }

    return null;
}

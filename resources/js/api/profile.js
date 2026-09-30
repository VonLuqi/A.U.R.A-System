/**
 * Profile self-service API — Etapa I (PLAN_PERFIL_BRANDING §3.1).
 * Sem Sanctum. Session cookie + CSRF via `api/client.js`.
 *
 * | Método | Path | Resposta |
 * | --- | --- | --- |
 * | PATCH | `/api/profile` | `{ user: AuthUser }` |
 * | POST | `/api/profile/avatar` | `{ user: AuthUser }` · 201 |
 * | DELETE | `/api/profile/avatar` | `{ user: AuthUser }` |
 *
 * `email` é somente leitura no self-service — não enviar no body.
 *
 * @typedef {import('../lib/auth').AuthUser} AuthUser
 *
 * @typedef {{
 *   name?: string,
 *   password?: string,
 *   password_confirmation?: string,
 *   current_password?: string,
 * }} UpdateProfilePayload
 */
import api from './client';

/**
 * Atualiza nome e/ou senha do usuário autenticado.
 *
 * @param {UpdateProfilePayload} payload
 * @returns {Promise<AuthUser>}
 */
export async function updateProfile(payload) {
    const body = {};

    if (payload.name !== undefined) {
        body.name = payload.name;
    }

    if (payload.password !== undefined) {
        body.password = payload.password;
        body.password_confirmation = payload.password_confirmation;
        body.current_password = payload.current_password;
    }

    const { data } = await api.patch('/api/profile', body);

    return data.user;
}

/**
 * Upload de avatar (JPEG/PNG/WebP ≤ 2 MB).
 * Não setar Content-Type — o browser define multipart boundary (`client.js`).
 *
 * @param {File|Blob} file
 * @returns {Promise<AuthUser>}
 */
export async function uploadAvatar(file) {
    const formData = new FormData();
    formData.append('avatar', file);

    const { data } = await api.post('/api/profile/avatar', formData);

    return data.user;
}

/**
 * Remove o avatar do usuário autenticado (idempotente).
 *
 * @returns {Promise<AuthUser>}
 */
export async function deleteAvatar() {
    const { data } = await api.delete('/api/profile/avatar');

    return data.user;
}

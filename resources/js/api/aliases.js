/**
 * Aliases API — PLAN_EXPANSAO §4.2 / §8.7.
 *
 * | Método | Path | Resposta |
 * | --- | --- | --- |
 * | GET | `/api/aliases` | `{ data, meta }` |
 * | POST | `/api/aliases` | `{ data, retroactive? }` · 201 |
 * | POST | `/api/aliases/preview` | `{ data: null \| PreviewMatch }` |
 * | PATCH | `/api/aliases/{id}` | `{ data }` |
 * | DELETE | `/api/aliases/{id}` | `{ message }` |
 *
 * @typedef {'exact'|'contains'|'starts_with'|'regex'} AliasMatchType
 *
 * @typedef {{
 *   id: number,
 *   name: string,
 *   slug?: string,
 *   type?: string,
 *   color?: string|null,
 * }} AliasCategory
 *
 * @typedef {{
 *   id: number,
 *   match_type: AliasMatchType|string,
 *   match_pattern: string,
 *   display_name: string,
 *   category_id: number|null,
 *   category?: AliasCategory|null,
 *   priority: number,
 *   is_active: boolean,
 *   created_at?: string,
 *   updated_at?: string,
 * }} Alias
 *
 * @typedef {{
 *   match_type: AliasMatchType,
 *   match_pattern: string,
 *   display_name: string,
 *   category_id?: number|null,
 *   priority?: number,
 *   is_active?: boolean,
 *   apply_to_existing?: boolean,
 * }} AliasWritePayload
 *
 * @typedef {{
 *   alias_id: number,
 *   display_name: string,
 *   category_id: number|null,
 * }} AliasPreviewMatch
 *
 * @typedef {{
 *   scanned: number,
 *   updated: number,
 *   limit: number,
 * }} AliasRetroactive
 */
import api from './client';

/**
 * @param {Record<string, string|number|boolean|undefined>} [params]
 * @param {{ signal?: AbortSignal }} [options]
 * @returns {Promise<{ data: Alias[], meta: object }>}
 */
export async function listAliases(params = {}, { signal } = {}) {
    const { data } = await api.get('/api/aliases', { params, signal });

    return data;
}

/**
 * @param {AliasWritePayload} payload
 * @returns {Promise<{ data: Alias, retroactive?: AliasRetroactive }>}
 */
export async function createAlias(payload) {
    const { data } = await api.post('/api/aliases', payload);

    return data;
}

/**
 * @param {number|string} id
 * @param {Partial<AliasWritePayload>} payload
 * @returns {Promise<Alias>}
 */
export async function updateAlias(id, payload) {
    const { data } = await api.patch(`/api/aliases/${id}`, payload);

    return data.data;
}

/**
 * @param {number|string} id
 * @returns {Promise<{ message: string }>}
 */
export async function deleteAlias(id) {
    const { data } = await api.delete(`/api/aliases/${id}`);

    return data;
}

/**
 * @param {{ description: string }} payload
 * @param {{ signal?: AbortSignal }} [options]
 * @returns {Promise<AliasPreviewMatch|null>}
 */
export async function previewAlias(payload, { signal } = {}) {
    const { data } = await api.post('/api/aliases/preview', payload, { signal });

    return data.data ?? null;
}

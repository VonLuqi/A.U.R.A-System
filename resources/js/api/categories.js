/**
 * Categories API — Etapa D §5.1 (cache em memória) + CRUD gestão.
 *
 * | Método | Path | Resposta |
 * | --- | --- | --- |
 * | GET | `/api/categories` | `{ data: Category[] }` |
 * | POST | `/api/categories` | `{ data }` · 201 |
 * | PATCH | `/api/categories/{id}` | `{ data }` |
 * | DELETE | `/api/categories/{id}` | `{ message }` |
 *
 * @typedef {{
 *   id: number,
 *   name: string,
 *   slug: string,
 *   type: 'income'|'expense'|'transfer'|string,
 *   color: string|null,
 *   is_system?: boolean,
 *   transactions_count?: number,
 *   goals_count?: number,
 *   aliases_count?: number,
 *   usage_count?: number,
 * }} Category
 */
import api from './client';

/** @type {Category[]|null} */
let categoriesCache = null;

/** @type {Promise<Category[]>|null} */
let categoriesPromise = null;

/**
 * Lista categorias (fetch once; use `force` para invalidar).
 *
 * @param {{ force?: boolean, signal?: AbortSignal }} [options]
 * @returns {Promise<Category[]>}
 */
export async function listCategories({ force = false, signal } = {}) {
    if (!force && categoriesCache) {
        return categoriesCache;
    }

    if (!force && categoriesPromise) {
        return categoriesPromise;
    }

    categoriesPromise = api
        .get('/api/categories', { signal })
        .then(({ data }) => {
            categoriesCache = data.data ?? [];

            return categoriesCache;
        })
        .finally(() => {
            categoriesPromise = null;
        });

    return categoriesPromise;
}

/**
 * @param {{ name: string, type?: string, color?: string|null }} payload
 * @returns {Promise<Category>}
 */
export async function createCategory(payload) {
    const { data } = await api.post('/api/categories', payload);
    const category = data.data;
    clearCategoriesCache();

    return category;
}

/**
 * @param {number|string} id
 * @param {{ name: string, type?: string, color?: string|null }} payload
 * @returns {Promise<Category>}
 */
export async function updateCategory(id, payload) {
    const { data } = await api.patch(`/api/categories/${id}`, payload);
    clearCategoriesCache();

    return data.data;
}

/**
 * @param {number|string} id
 * @returns {Promise<{ message: string }>}
 */
export async function deleteCategory(id) {
    const { data } = await api.delete(`/api/categories/${id}`);
    clearCategoriesCache();

    return data;
}

export function clearCategoriesCache() {
    categoriesCache = null;
    categoriesPromise = null;
}

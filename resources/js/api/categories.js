/**
 * Categories API — Etapa D §5.1 (cache em memória).
 *
 * `GET /api/categories` → `{ data: Category[] }`
 *
 * @typedef {{
 *   id: number,
 *   name: string,
 *   slug: string,
 *   type: string,
 *   color: string|null,
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
    const { data } = await api.post('/categories', payload);
    const category = data.data;
    clearCategoriesCache();

    return category;
}

export function clearCategoriesCache() {
    categoriesCache = null;
    categoriesPromise = null;
}

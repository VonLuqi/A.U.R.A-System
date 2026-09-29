/**
 * Merge className fragments (Etapa D §5.4).
 * @param {...(string|false|null|undefined)} parts
 * @returns {string}
 */
export function cx(...parts) {
    return parts.filter(Boolean).join(' ');
}

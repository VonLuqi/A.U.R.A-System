import { useEffect, useState } from 'react';

/**
 * useMediaQuery — Etapa D §6 (Toaster / responsividade).
 * @param {string} query
 */
export function useMediaQuery(query) {
    const [matches, setMatches] = useState(() => {
        if (typeof window === 'undefined' || !window.matchMedia) {
            return false;
        }

        return window.matchMedia(query).matches;
    });

    useEffect(() => {
        if (typeof window === 'undefined' || !window.matchMedia) {
            return undefined;
        }

        const media = window.matchMedia(query);
        const onChange = () => setMatches(media.matches);

        onChange();
        media.addEventListener('change', onChange);

        return () => media.removeEventListener('change', onChange);
    }, [query]);

    return matches;
}

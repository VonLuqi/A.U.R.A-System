import { useEffect } from 'react';

/**
 * Define `document.title` — Etapa D §5.5.
 * @param {string} title ex.: `Login · Aura`
 */
export function useDocumentTitle(title) {
    useEffect(() => {
        const previous = document.title;
        document.title = title;

        return () => {
            document.title = previous;
        };
    }, [title]);
}

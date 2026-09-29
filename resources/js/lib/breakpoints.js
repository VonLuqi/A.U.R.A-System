/**
 * Breakpoints Aura — Etapa D §6.1 (Tailwind default).
 *
 * | Nome    | Largura     | Comportamento                                      |
 * | ------- | ----------- | -------------------------------------------------- |
 * | mobile  | < 640px     | stack; nav hamburger; charts full; tabela scroll-X |
 * | tablet  | 640–1023px  | cards 2×2; charts stack até lg                     |
 * | desktop | ≥ 1024px    | layout §4.8 (charts 2 col); cards 4 col em xl      |
 *
 * Tailwind: sm=640 · md=768 · lg=1024 · xl=1280
 */

export const BREAKPOINTS = {
    sm: 640,
    md: 768,
    lg: 1024,
    xl: 1280,
};

/** Media query: viewport mobile (§6.1 <640px). */
export const MQ_MOBILE = `(max-width: ${BREAKPOINTS.sm - 1}px)`;

/** Media query: tablet e acima. */
export const MQ_TABLET_UP = `(min-width: ${BREAKPOINTS.sm}px)`;

/** Media query: desktop e acima. */
export const MQ_DESKTOP_UP = `(min-width: ${BREAKPOINTS.lg}px)`;

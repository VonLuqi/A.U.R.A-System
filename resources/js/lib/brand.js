/**
 * Brand mark tokens — Etapa I §4.1 (spec visual travada).
 * Consumidos pelo SVG inline em BrandMark (§4.2).
 * Não embutir wordmark nem tagline no SVG.
 */

/** @type {const} */
export const BRAND_MARK = Object.freeze({
    viewBox: '0 0 32 32',
    /** `color.brand.primary` — traços e núcleo */
    color: '#DCCFFF',
    /** Canvas de contraste (UI); SVG permanece transparente */
    canvas: '#151716',
    opacity: Object.freeze({
        core: 1,
        ringInner: 0.85,
        ringOuter: 0.45,
        halo: 0.28,
    }),
});

/** @type {import('tailwindcss').Config} */
/**
 * Tailwind ↔ Design System (Etapa D §7.1).
 * Cores / espaços / radius / fontSize → CSS variables em `tokens.css`.
 */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{js,jsx}',
        './resources/**/*.blade.php',
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
    ],
    theme: {
        extend: {
            // Screens: defaults Tailwind (Etapa D §6.1)
            // sm 640 · md 768 · lg 1024 · xl 1280 · 2xl 1536
            // mobile <640 · tablet 640–1023 · desktop ≥1024
            fontFamily: {
                sans: ['var(--font-sans)'],
            },
            colors: {
                brand: {
                    DEFAULT: 'var(--color-brand-primary)',
                    muted: 'var(--color-brand-muted)',
                    glow: 'var(--color-brand-glow)',
                },
                canvas: 'var(--color-bg-default)',
                surface: {
                    DEFAULT: 'var(--color-surface-default)',
                    raised: 'var(--color-surface-raised)',
                    sunken: 'var(--color-surface-sunken)',
                    inverse: 'var(--color-surface-inverse)',
                },
                border: {
                    subtle: 'var(--color-border-subtle)',
                    DEFAULT: 'var(--color-border-default)',
                },
                ink: {
                    DEFAULT: 'var(--color-text-primary)',
                    secondary: 'var(--color-text-secondary)',
                    muted: 'var(--color-text-muted)',
                    'on-brand': 'var(--color-text-on-brand)',
                    'on-inverse': 'var(--color-text-on-inverse)',
                    disabled: 'var(--color-text-disabled)',
                },
                interactive: {
                    primary: 'var(--color-interactive-primary)',
                    inverse: 'var(--color-interactive-inverse)',
                    dark: 'var(--color-interactive-dark)',
                },
                feedback: {
                    neutral: 'var(--color-feedback-neutral)',
                    positive: 'var(--color-feedback-positive)',
                    danger: 'var(--color-feedback-danger)',
                },
                overlay: {
                    scrim: 'var(--color-overlay-scrim)',
                },
            },
            fontSize: {
                display: ['var(--text-display)', { lineHeight: '1.1', letterSpacing: '-0.02em' }],
                h1: ['var(--text-h1)', { lineHeight: '1.25', letterSpacing: '-0.01em' }],
                h2: ['var(--text-h2)', { lineHeight: '1.3' }],
                h3: ['var(--text-h3)', { lineHeight: '1.35' }],
                body: ['var(--text-body)', { lineHeight: '1.5' }],
                'body-lg': ['var(--text-body-lg)', { lineHeight: '1.5' }],
                caption: ['var(--text-caption)', { lineHeight: '1.4', letterSpacing: '0.01em' }],
                small: ['var(--text-small)', { lineHeight: '1.35', letterSpacing: '0.02em' }],
            },
            borderRadius: {
                sm: 'var(--radius-sm)',
                md: 'var(--radius-md)',
                lg: 'var(--radius-lg)',
                xl: 'var(--radius-xl)',
                '2xl': 'var(--radius-2xl)',
                full: 'var(--radius-full)',
            },
            spacing: {
                1: 'var(--space-1)',
                2: 'var(--space-2)',
                3: 'var(--space-3)',
                4: 'var(--space-4)',
                5: 'var(--space-5)',
                6: 'var(--space-6)',
                8: 'var(--space-8)',
                10: 'var(--space-10)',
                12: 'var(--space-12)',
            },
        },
    },
    plugins: [],
};

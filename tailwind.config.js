/**
 * Admin "Ledger" theme wiring (admin-section-ui.md:3, step 3.4).
 *
 * Tailwind 4 loads this JS config via the `@config` directive in
 * resources/css/app.css. The theme below mirrors `docs/mockups/admin/
 * tailwind-preset.js` (the committed visual-reference authority — the
 * mockup file itself is CommonJS and cannot be imported from this ESM
 * config, which is a `"type": "module"` project; same copy-mirroring
 * approach as `resources/css/public-tokens.css`, see ADR-0023). Keep the two
 * in sync.
 *
 * Note: Tailwind 4 no longer supports v3-style `darkMode: 'class'` from
 * JS configs (inert here) — the class-based dark variant is declared in
 * app.css via `@custom-variant dark`.
 */
export default {
    darkMode: 'class',
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
                mono: ['JetBrains Mono', 'Fira Code', 'Cascadia Code', 'monospace'],
            },

            colors: {
                primary: {
                    50: '#EEF3FE',
                    100: '#DCE7FD',
                    200: '#B9CFFB',
                    300: '#8FB0F7',
                    400: '#5C87EF',
                    500: '#3663E3',
                    600: '#2447C9',
                    700: '#1B369E',
                    800: '#16297A',
                    900: '#142363',
                    950: '#0C1538',
                },

                surface: {
                    DEFAULT: '#ffffff',
                    secondary: '#F6F8FC',
                    tertiary: '#EBF0FA',
                    dark: '#0F1830',
                    'dark-secondary': '#16213D',
                },

                sidebar: {
                    DEFAULT: '#0B1224',
                    hover: '#16213D',
                    active: '#2447C9',
                    border: '#1D2846',
                    text: '#8993B5',
                    'text-active': '#E7ECFC',
                },

                muted: {
                    DEFAULT: '#94a3b8',
                    dark: '#64748b',
                },

                success: {
                    50: '#f0fdf4',
                    100: '#dcfce7',
                    500: '#22c55e',
                    600: '#16a34a',
                    700: '#15803d',
                },

                warning: {
                    50: '#fffbeb',
                    100: '#fef3c7',
                    500: '#f59e0b',
                    600: '#d97706',
                    700: '#b45309',
                },

                danger: {
                    50: '#fef2f2',
                    100: '#fee2e2',
                    500: '#ef4444',
                    600: '#dc2626',
                    700: '#b91c1c',
                },
            },

            fontSize: {
                '2xs': ['0.65rem', { lineHeight: '1rem' }],
                xs: ['0.75rem', { lineHeight: '1rem' }],
                sm: ['0.875rem', { lineHeight: '1.25rem' }],
                base: ['1rem', { lineHeight: '1.5rem' }],
                lg: ['1.125rem', { lineHeight: '1.75rem' }],
                xl: ['1.25rem', { lineHeight: '1.75rem' }],
                '2xl': ['1.5rem', { lineHeight: '2rem' }],
                '3xl': ['1.875rem', { lineHeight: '2.25rem' }],
                '4xl': ['2.25rem', { lineHeight: '2.5rem' }],
            },

            borderRadius: {
                sm: '0.25rem',
                md: '0.5rem',
                lg: '0.75rem',
                xl: '1rem',
                full: '9999px',
            },

            boxShadow: {
                card: '0 1px 3px rgba(12, 21, 56, 0.07), 0 1px 2px rgba(12, 21, 56, 0.05)',
                'card-hover': '0 6px 16px rgba(12, 21, 56, 0.10), 0 2px 6px rgba(12, 21, 56, 0.05)',
                modal: '0 24px 64px rgba(12, 21, 56, 0.22), 0 8px 20px rgba(12, 21, 56, 0.1)',
                dropdown: '0 10px 28px rgba(12, 21, 56, 0.14), 0 2px 8px rgba(12, 21, 56, 0.07)',
                'dark-card': '0 1px 3px rgba(0, 0, 0, 0.3), 0 1px 2px rgba(0, 0, 0, 0.15)',
                'signal-glow': '0 0 0 3px rgba(54, 99, 227, 0.25)',
            },

            spacing: {
                '4.5': '1.125rem',
                18: '4.5rem',
                88: '22rem',
            },

            maxWidth: {
                'container-sm': '640px',
                'container-md': '768px',
                'container-lg': '1024px',
                'container-xl': '1200px',
            },

            transitionDuration: {
                fast: '150ms',
                normal: '300ms',
                slow: '500ms',
            },

            zIndex: {
                dropdown: '100',
                sticky: '200',
                overlay: '250',
                modal: '300',
                toast: '400',
            },
        },
    },
};

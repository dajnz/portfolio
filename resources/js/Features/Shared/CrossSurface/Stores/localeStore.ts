import { defineStore } from 'pinia';
import { ref } from 'vue';

/**
 * Supported locales — mirrors `config/portfolio.php` `supported_locales`.
 */
export const SUPPORTED_LOCALES = ['en', 'ru'] as const;

export type Locale = (typeof SUPPORTED_LOCALES)[number];

/**
 * Global locale state. Hydrated from the server-shared `locale` prop on
 * every Inertia visit; content text itself stays server-resolved per
 * request — only the UI chrome translation locale lives here.
 */
export const useLocaleStore = defineStore('locale', () => {
    const locale = ref<Locale>('en');

    function hydrate(value: unknown): void {
        if (SUPPORTED_LOCALES.includes(value as Locale)) {
            locale.value = value as Locale;
        }
    }

    function setLocale(value: string): void {
        if (SUPPORTED_LOCALES.includes(value as Locale)) {
            locale.value = value as Locale;
        }
    }

    return { locale, hydrate, setLocale };
});

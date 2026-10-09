import { router } from '@inertiajs/vue3';
import { type Locale, SUPPORTED_LOCALES, useLocaleStore } from '@shared-cross/Stores/localeStore';
import { IntlMessageFormat } from 'intl-messageformat';
import { computed } from 'vue';
import en from '@/lang/en.json';
import ru from '@/lang/ru.json';

/**
 * Frontend i18n composable: resolves dot-path keys from the committed
 * `lang/{en,ru}.json` catalogs, resolves ICU MessageFormat plural/select
 * via intl-messageformat (cached per key+locale), interpolates `:param`
 * placeholders (Laravel-style, used by keys like `admin.feedback.from`),
 * and falls back to the key itself when a translation is missing.
 */
export type TranslationParams = Record<string, string | number | boolean>;

const catalogs: Record<string, unknown> = { en, ru };

const LOCALE_COOKIE = 'locale';
const LOCALE_COOKIE_MAX_AGE = 31536000;

/** Compiled message instances per key+locale. */
const messageCache = new Map<string, IntlMessageFormat>();

function lookupPath(catalog: unknown, key: string): string | null {
    let node: unknown = catalog;

    for (const segment of key.split('.')) {
        if (node === null || typeof node !== 'object') {
            return null;
        }

        node = (node as Record<string, unknown>)[segment];
    }

    return typeof node === 'string' ? node : null;
}

function interpolateColonParams(message: string, params: TranslationParams): string {
    return message.replace(/:([a-zA-Z0-9_]+)/g, (match, name: string) => {
        const value = params[name];

        return value === undefined ? match : String(value);
    });
}

export function useI18n(): {
    locale: import('vue').ComputedRef<Locale>;
    t: (key: string, params?: TranslationParams) => string;
    setLocale: (next: string) => void;
} {
    const localeStore = useLocaleStore();
    const locale = computed(() => localeStore.locale);

    function t(key: string, params?: TranslationParams): string {
        const message = lookupPath(catalogs[locale.value], key) ?? key;

        if (params === undefined) {
            return message;
        }

        if (message.includes('{')) {
            const cacheKey = `${locale.value}:${key}`;
            let formatter = messageCache.get(cacheKey);
            if (!formatter) {
                formatter = new IntlMessageFormat(message, locale.value);
                messageCache.set(cacheKey, formatter);
            }

            return String(formatter.format(params));
        }

        return interpolateColonParams(message, params);
    }

    function setLocale(next: string): void {
        if (!SUPPORTED_LOCALES.includes(next as Locale)) {
            return;
        }

        localeStore.setLocale(next);

        if (typeof document !== 'undefined') {
            // biome-ignore lint/suspicious/noDocumentCookie: spec-mandated `locale` cookie
            document.cookie = `${LOCALE_COOKIE}=${next};path=/;max-age=${LOCALE_COOKIE_MAX_AGE};SameSite=Lax`;
        }

        // Reload the page with fresh server-side props.
        router.reload();
    }

    return { locale, t, setLocale };
}

/**
 * Locales referenced by a server error map — the per-locale validation
 * keys carry the `field.{locale}` shape (e.g. `name.ru`). Used to build
 * the "translation incomplete" toasts and the warned locale tabs.
 */
export function localesWithErrors(errors: Record<string, string>): string[] {
    return Object.keys(errors)
        .map((key) => key.match(/\.(en|ru)$/)?.[1])
        .filter((locale): locale is string => locale !== undefined)
        .filter((locale, index, all) => all.indexOf(locale) === index);
}

// Shared locale-options helper — the admin surfaces receive the ACTIVE
// locales (the `supported_locales` setting) via the shared
// `supported_locales` prop; this maps the codes to the {code,
// native_name} options the LanguageSelector / LocaleTabs consume.
// Falls back to the full en/ru pair when the shared prop is absent (the
// settings are unreachable or the value is empty).
export interface LocaleOption {
    code: string;
    native_name: string;
}

const LOCALE_NAMES: Record<string, string> = {
    en: 'English',
    ru: 'Русский',
};

export function localeOptionsFromCodes(codes?: string[] | null): LocaleOption[] {
    const active = codes !== undefined && codes !== null && codes.length > 0 ? codes : ['en', 'ru'];

    return active.map((code) => ({ code, native_name: LOCALE_NAMES[code] ?? code }));
}

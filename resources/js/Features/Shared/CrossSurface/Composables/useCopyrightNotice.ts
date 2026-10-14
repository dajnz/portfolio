import { usePage } from '@inertiajs/vue3';
import { type ComputedRef, computed } from 'vue';

import { useI18n } from './useI18n';

/**
 * The site's first published year — the copyright range always starts
 * here and gains a second year once the current year moves past it.
 */
const COPYRIGHT_START_YEAR = 2026;

type CopyrightSharedProps = {
    copyright?: {
        owner_name?: string;
    };
};

/**
 * The localized footer copyright notice shared by the public and admin
 * footers: `© {start}` or `© {start}–{current}` followed by the
 * locale-resolved owner name (from the shared `copyright` prop).
 */
export function useCopyrightNotice(): ComputedRef<string> {
    const { t } = useI18n();
    const page = usePage<CopyrightSharedProps>();

    return computed(() => {
        const ownerName = page.props?.copyright?.owner_name ?? '';
        const currentYear = new Date().getFullYear();
        const years =
            currentYear > COPYRIGHT_START_YEAR
                ? `${COPYRIGHT_START_YEAR}–${currentYear}`
                : String(COPYRIGHT_START_YEAR);
        const ownerYear = ownerName === '' ? years : `${years} ${ownerName}`;

        return t('landing.footer_copyright', { owner_year: ownerYear });
    });
}

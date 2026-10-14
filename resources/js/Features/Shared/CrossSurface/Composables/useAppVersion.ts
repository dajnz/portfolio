import { usePage } from '@inertiajs/vue3';
import { type ComputedRef, computed } from 'vue';

import { useI18n } from './useI18n';

type AppVersionSharedProps = {
    version?: string;
};

/**
 * The localized admin footer version label (`Version: {version}`) built from
 * the shared `version` prop (the deployed git tag or short commit SHA).
 * Returns an empty string when no version is shared so callers can omit it.
 */
export function useAppVersion(): ComputedRef<string> {
    const { t } = useI18n();
    const page = usePage<AppVersionSharedProps>();

    return computed(() => {
        const version = page.props?.version ?? '';

        return version === '' ? '' : t('admin.footer_version', { version });
    });
}

import type { FeatureFlagData } from '@settings-admin/FeatureFlagToggle.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useJsonApi } from '@shared-cross/Composables/useJsonApi';
import { useToast } from '@shared-cross/Composables/useToast';
import { type ComputedRef, computed, reactive } from 'vue';

const FLAG_KEYS = ['github_oauth', 'social_reactions', 'console_greeting'] as const;

export interface UseFeatureFlagsApi {
    flagRows: ComputedRef<FeatureFlagData[]>;
    flagHints: ComputedRef<Record<string, string>>;
    onFlagToggle: (payload: { key: string; is_enabled: boolean }) => Promise<void>;
}

export function useFeatureFlags(
    initialFlags: () => Record<string, boolean> | undefined,
): UseFeatureFlagsApi {
    const { t } = useI18n();
    const toast = useToast();
    const { patch } = useJsonApi();

    const flags = reactive<Record<string, boolean>>({ ...initialFlags() });

    const flagRows = computed<FeatureFlagData[]>(() =>
        FLAG_KEYS.map((key) => ({
            key,
            name: t(`admin.settings.${key}`),
            is_enabled: flags[key] ?? false,
        })),
    );

    const flagHints = computed<Record<string, string>>(() =>
        Object.fromEntries(FLAG_KEYS.map((key) => [key, t(`admin.settings.${key}_hint`)])),
    );

    async function onFlagToggle(payload: { key: string; is_enabled: boolean }): Promise<void> {
        const previous = flags[payload.key] ?? false;

        flags[payload.key] = payload.is_enabled;

        try {
            await patch<{ data: { flags: Record<string, boolean> } }>(
                '/admin/settings/feature-flags',
                { json: { flags: { [payload.key]: payload.is_enabled } } },
            );
        } catch {
            flags[payload.key] = previous;
            toast.show(t('admin.toasts.failed_to_save'), { variant: 'error' });
        }
    }

    return { flagRows, flagHints, onFlagToggle };
}

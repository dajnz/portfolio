import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useToast } from '@shared-cross/Composables/useToast';
import { useUnsavedChanges } from '@shared-cross/Composables/useUnsavedChanges';
import { SUPPORTED_LOCALES } from '@shared-cross/Stores/localeStore';
import { type ComputedRef, computed, type Ref, reactive, ref } from 'vue';

import type { SettingsFormValues, SettingsPayload, TabId } from './types';

export interface UseSettingsFormOptions {
    settings: () => Record<string, unknown>;
    activeTab: () => TabId;
}

export interface UseSettingsFormApi {
    form: SettingsFormValues;
    errors: Ref<Record<string, string>>;
    isSaving: Ref<boolean>;
    headerLocales: ComputedRef<Array<{ code: string; native_name: string }>>;
    disabledLocales: ComputedRef<string[]>;
    mailPayload: () => SettingsPayload;
    toggleLocale: (locale: string, checked: boolean) => void;
    save: () => void;
}

// Keys owned by each form tab — used to move the saved tab's keys into
// the guard's baseline after a successful save.
const TAB_KEYS: Record<Exclude<TabId, 'flags'>, Array<keyof SettingsFormValues>> = {
    general: [
        'site_maintenance_mode',
        'supported_locales',
        'items_per_page',
        'projects_per_page',
        'articles_per_page',
        'experience_items_per_page',
        'trash_retention_days',
        'backup_max_count',
        'backup_upload_max_size',
        'image_max_dimension',
    ],
    mail: [
        'mail_api_endpoint',
        'mail_api_token',
        'mail_from_address',
        'mail_from_name',
        'email_on_feedback',
    ],
    analytics: ['analytics_email_enabled', 'analytics_email_recipient'],
};

function toBool(value: unknown, fallback: boolean): boolean {
    return typeof value === 'boolean' ? value : fallback;
}

function toInt(value: unknown, fallback: number): number {
    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : fallback;
}

function toText(value: unknown, fallback: string): string {
    return typeof value === 'string' ? value : fallback;
}

function toNumberText(value: unknown, fallback: number): string {
    const parsed = Number(value);

    return Number.isFinite(parsed) ? String(parsed) : String(fallback);
}

function toLocaleList(value: unknown): string[] {
    return Array.isArray(value)
        ? value.filter((locale): locale is string => typeof locale === 'string')
        : [...SUPPORTED_LOCALES];
}

function buildForm(settings: Record<string, unknown>): SettingsFormValues {
    return {
        site_maintenance_mode: toBool(settings.site_maintenance_mode, false),
        supported_locales: toLocaleList(settings.supported_locales),
        items_per_page: toNumberText(settings.items_per_page, 12),
        projects_per_page: toNumberText(settings.projects_per_page, 12),
        articles_per_page: toNumberText(settings.articles_per_page, 12),
        experience_items_per_page: toNumberText(settings.experience_items_per_page, 15),
        trash_retention_days: toNumberText(settings.trash_retention_days, 30),
        backup_max_count: toNumberText(settings.backup_max_count, 30),
        backup_upload_max_size: toNumberText(settings.backup_upload_max_size, 102400),
        image_max_dimension: toNumberText(settings.image_max_dimension, 2048),
        mail_api_endpoint: toText(settings.mail_api_endpoint, ''),
        mail_api_token: toText(settings.mail_api_token, ''),
        mail_from_address: toText(settings.mail_from_address, ''),
        mail_from_name: toText(settings.mail_from_name, ''),
        email_on_feedback: toBool(settings.email_on_feedback, true),
        analytics_email_enabled: toBool(settings.analytics_email_enabled, false),
        analytics_email_recipient: toText(settings.analytics_email_recipient, ''),
    };
}

export function useSettingsForm(options: UseSettingsFormOptions): UseSettingsFormApi {
    const { t, setLocale } = useI18n();
    const toast = useToast();
    const page = usePage<{ locale?: string }>();

    const form = reactive<SettingsFormValues>(buildForm(options.settings()));
    const baseline = buildForm(options.settings()) as unknown as Record<string, unknown>;

    const isSaving = ref(false);
    const errors = ref<Record<string, string>>({});

    // The guard tracks the whole settings map; per-tab saves re-baseline only
    // the saved tab's keys (rebase), so edits on the other tabs stay armed.
    const unsavedChanges = useUnsavedChanges(form as unknown as Record<string, unknown>, baseline);

    // The header language selector mirrors the form's supported locales
    // live: unchecking a locale immediately narrows the selector to the
    // remaining ones.
    const headerLocales = computed<Array<{ code: string; native_name: string }>>(() =>
        SUPPORTED_LOCALES.filter((code) => form.supported_locales.includes(code)).map((code) => ({
            code,
            native_name: t(`admin.locales.${code}`),
        })),
    );

    // At least one locale must stay enabled — the checkbox of the last
    // remaining locale is disabled (the backend enforces the same rule).
    const disabledLocales = computed<string[]>(() =>
        form.supported_locales.length === 1 ? [...form.supported_locales] : [],
    );

    function isLastLocale(locale: string): boolean {
        return form.supported_locales.length === 1 && form.supported_locales.includes(locale);
    }

    function toggleLocale(locale: string, checked: boolean): void {
        if (!checked && isLastLocale(locale)) {
            return;
        }

        const next = new Set(form.supported_locales);

        if (checked) {
            next.add(locale);
        } else {
            next.delete(locale);
        }

        form.supported_locales = [...next];
    }

    // When a save disables the currently active locale, the actual language
    // switches to a remaining one (cookie + reload — all UI translations
    // update); otherwise the selector-only narrowing above suffices.
    function syncLocaleWithSupported(locales: string[]): void {
        const activeLocale = page.props?.locale ?? 'en';

        if (locales.length === 0 || locales.includes(activeLocale)) {
            return;
        }

        setLocale(locales[0]);
    }

    function generalPayload(): SettingsPayload {
        return {
            site_maintenance_mode: form.site_maintenance_mode,
            supported_locales: form.supported_locales,
            items_per_page: toInt(form.items_per_page, 1),
            projects_per_page: toInt(form.projects_per_page, 1),
            articles_per_page: toInt(form.articles_per_page, 1),
            experience_items_per_page: toInt(form.experience_items_per_page, 1),
            trash_retention_days: toInt(form.trash_retention_days, 0),
            backup_max_count: toInt(form.backup_max_count, 1),
            backup_upload_max_size: toInt(form.backup_upload_max_size, 1),
            image_max_dimension: toInt(form.image_max_dimension, 2048),
        };
    }

    function mailPayload(): SettingsPayload {
        return {
            mail_api_endpoint: form.mail_api_endpoint,
            mail_api_token: form.mail_api_token,
            mail_from_address: form.mail_from_address,
            mail_from_name: form.mail_from_name,
            email_on_feedback: form.email_on_feedback,
        };
    }

    function analyticsPayload(): SettingsPayload {
        return {
            analytics_email_enabled: form.analytics_email_enabled,
            analytics_email_recipient: form.analytics_email_recipient,
        };
    }

    const activePayload = computed<SettingsPayload>(() => {
        if (options.activeTab() === 'general') {
            return generalPayload();
        }

        if (options.activeTab() === 'mail') {
            return mailPayload();
        }

        return analyticsPayload();
    });

    function save(): void {
        const tab = options.activeTab();

        if (isSaving.value || tab === 'flags') {
            return;
        }

        isSaving.value = true;
        errors.value = {};

        // The guard must not prompt for the form's own submit.
        unsavedChanges.suppressNextVisit();

        router.put(
            '/admin/settings',
            { settings: activePayload.value },
            {
                preserveScroll: true,
                onSuccess: () => {
                    isSaving.value = false;

                    // The saved tab's values become the new baseline — the
                    // other tabs' unsaved edits stay armed.
                    for (const key of TAB_KEYS[tab]) {
                        baseline[key] = form[key];
                    }
                    unsavedChanges.rebase(baseline);

                    // A save that removed the active locale switches the
                    // language to a remaining one (cookie + reload).
                    if (tab === 'general') {
                        syncLocaleWithSupported(form.supported_locales);
                    }

                    toast.show(t('admin.toasts.settings_saved'));
                },
                onError: (serverErrors) => {
                    isSaving.value = false;
                    errors.value = { ...serverErrors };
                },
            },
        );
    }

    return {
        form,
        errors,
        isSaving,
        headerLocales,
        disabledLocales,
        mailPayload,
        toggleLocale,
        save,
    };
}

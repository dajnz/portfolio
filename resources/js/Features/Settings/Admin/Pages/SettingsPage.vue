<!-- SettingsPage — tabbed settings form: General (Site Status / Languages / Content settings / Pagination / Data Retention), Mail (API Connection with the Test Connection probe + Notifications), Feature Flags (FeatureFlagToggle rows, PATCH endpoint), Analytics (Reports). Each form tab submits a grouped `{ settings: { key: value } }` payload to PUT /admin/settings. -->
<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import FeatureFlagToggle, {
    type FeatureFlagData,
} from '@settings/Admin/Components/FeatureFlagToggle.vue';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminBaseCheckbox from '@shared-admin/AdminBaseCheckbox.vue';
import AdminBaseInput from '@shared-admin/AdminBaseInput.vue';
import AdminBaseSwitch from '@shared-admin/AdminBaseSwitch.vue';
import AdminPageLayout from '@shared-admin/AdminPageLayout.vue';
import { useAdminMenu } from '@shared-cross/Composables/useAdminMenu';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useJsonApi } from '@shared-cross/Composables/useJsonApi';
import { useToast } from '@shared-cross/Composables/useToast';
import { useUnsavedChanges } from '@shared-cross/Composables/useUnsavedChanges';
import { SUPPORTED_LOCALES } from '@shared-cross/Stores/localeStore';
import { computed, reactive, ref } from 'vue';

type TabId = 'general' | 'mail' | 'flags' | 'analytics';

interface SettingsFormValues {
    site_maintenance_mode: boolean;
    supported_locales: string[];
    items_per_page: string;
    projects_per_page: string;
    articles_per_page: string;
    experience_items_per_page: string;
    trash_retention_days: string;
    backup_max_count: string;
    backup_upload_max_size: string;
    image_max_dimension: string;
    mail_api_endpoint: string;
    mail_api_token: string;
    mail_from_address: string;
    mail_from_name: string;
    email_on_feedback: boolean;
    analytics_email_enabled: boolean;
    analytics_email_recipient: string;
}

type SettingsValue = string | number | boolean | string[];

type SettingsPayload = Record<string, SettingsValue>;

const FLAG_KEYS = ['github_oauth', 'social_reactions', 'console_greeting'] as const;

const props = withDefaults(
    defineProps<{
        settings?: Record<string, unknown>;
        test_id?: string;
    }>(),
    {
        settings: () => ({}),
        test_id: 'settings-page',
    },
);

const { t, setLocale } = useI18n();
const toast = useToast();
const page = usePage<{ locale?: string; featureFlags?: Record<string, boolean> }>();
const { menuGroups } = useAdminMenu();
const { post, patch } = useJsonApi();

const TABS: Array<{ id: TabId; label_key: string }> = [
    { id: 'general', label_key: 'admin.settings.general' },
    { id: 'mail', label_key: 'admin.settings.mail' },
    { id: 'flags', label_key: 'admin.settings.feature_flags' },
    { id: 'analytics', label_key: 'admin.settings.analytics' },
];

// The last visited tab is remembered per browser (localStorage, same
// pattern as the admin sidebar collapse); the first tab is the fallback
// when nothing is stored yet or the stored value is not a known tab.
const TAB_STORAGE_KEY = 'admin_settings_active_tab';

function loadStoredTab(): TabId {
    if (typeof window === 'undefined') {
        return 'general';
    }

    const stored = window.localStorage.getItem(TAB_STORAGE_KEY);

    return TABS.some((tab) => tab.id === stored) ? (stored as TabId) : 'general';
}

const activeTab = ref<TabId>(loadStoredTab());

function selectTab(tab: TabId): void {
    activeTab.value = tab;

    if (typeof window !== 'undefined') {
        window.localStorage.setItem(TAB_STORAGE_KEY, tab);
    }
}

const isSaving = ref(false);
const isTesting = ref(false);
const errors = ref<Record<string, string>>({});

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

const form = reactive<SettingsFormValues>(buildForm(props.settings));
const baseline = buildForm(props.settings);

// The guard tracks the whole settings map; per-tab saves re-baseline only
// the saved tab's keys (rebase), so edits on the other tabs stay armed.
const unsavedChanges = useUnsavedChanges(
    form as unknown as Record<string, unknown>,
    baseline as unknown as Record<string, unknown>,
);

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

const flags = reactive<Record<string, boolean>>({ ...(page.props.featureFlags ?? {}) });

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

// The header language selector mirrors the form's supported locales
// live: unchecking a locale immediately narrows the selector to the
// remaining ones.
const headerLocales = computed<Array<{ code: string; native_name: string }>>(() =>
    SUPPORTED_LOCALES.filter((code) => form.supported_locales.includes(code)).map((code) => ({
        code,
        native_name: t(`admin.locales.${code}`),
    })),
);

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

// At least one locale must stay enabled — the checkbox of the last
// remaining locale is disabled (the backend enforces the same rule).
function isLastLocale(locale: string): boolean {
    return form.supported_locales.length === 1 && form.supported_locales.includes(locale);
}

function fieldError(field: string): string | null {
    return errors.value[`settings.${field}`] ?? null;
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
    if (activeTab.value === 'general') {
        return generalPayload();
    }

    if (activeTab.value === 'mail') {
        return mailPayload();
    }

    return analyticsPayload();
});

function save(): void {
    if (isSaving.value || activeTab.value === 'flags') {
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
                for (const key of TAB_KEYS[activeTab.value as Exclude<TabId, 'flags'>]) {
                    (baseline as unknown as Record<string, unknown>)[key] = form[key];
                }
                unsavedChanges.rebase(baseline as unknown as Record<string, unknown>);

                // A save that removed the active locale switches the
                // language to a remaining one (cookie + reload).
                if (activeTab.value === 'general') {
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

async function onFlagToggle(payload: { key: string; is_enabled: boolean }): Promise<void> {
    const previous = flags[payload.key] ?? false;

    flags[payload.key] = payload.is_enabled;

    try {
        await patch<{ data: { flags: Record<string, boolean> } }>('/admin/settings/feature-flags', {
            json: { flags: { [payload.key]: payload.is_enabled } },
        });
    } catch {
        flags[payload.key] = previous;
        toast.show(t('admin.toasts.failed_to_save'), { variant: 'error' });
    }
}

async function testMail(): Promise<void> {
    if (isTesting.value) {
        return;
    }

    isTesting.value = true;

    try {
        const data = await post<{
            data: { ok: boolean; status: 'ok' | 'unauthorized' | 'unreachable' };
        }>('/admin/settings/test-mail', { json: { settings: mailPayload() } });
        const result = data.data;

        if (result.ok) {
            toast.show(t('admin.settings.test_connection_ok'));
        } else {
            toast.show(t(`admin.settings.test_connection_${result.status}`), { variant: 'error' });
        }
    } catch {
        toast.show(t('admin.settings.test_connection_unreachable'), { variant: 'error' });
    } finally {
        isTesting.value = false;
    }
}
</script>

<template>
  <AdminPageLayout
    :menu_groups="menuGroups()"
    :active_route="'/admin/settings'"
    :locale_props="{ active_locale: page.props?.locale ?? 'en', supported_locales: headerLocales }"
    :data-testid="`${props.test_id}-layout`"
  >
    <section
      class="mx-auto max-w-3xl"
      :data-testid="props.test_id"
    >
      <h1 class="mb-6 text-2xl font-bold text-slate-900 dark:text-white">
        {{ t('admin.settings.title') }}
      </h1>

      <div
        class="thin-scrollbar mb-6 flex overflow-x-auto border-b border-slate-200 dark:border-slate-700"
        :data-testid="`${props.test_id}-tabs`"
      >
        <AdminBaseButton
          v-for="tab in TABS"
          :key="tab.id"
          :variant="'ghost'"
          :type="'button'"
          :class="
            activeTab === tab.id
              ? '-mb-px shrink-0 whitespace-nowrap rounded-none border-b-2 border-primary-600 text-primary-600 dark:text-primary-400'
              : 'shrink-0 whitespace-nowrap rounded-none'
          "
          :test_id="`${props.test_id}-tab-${tab.id}`"
          @click="selectTab(tab.id)"
        >
          {{ t(tab.label_key) }}
        </AdminBaseButton>
      </div>

      <form
        v-if="activeTab !== 'flags'"
        class="space-y-6 rounded-lg border border-slate-200 bg-white p-6 shadow-card dark:border-slate-700 dark:bg-surface-dark-secondary"
        novalidate
        :data-testid="`${props.test_id}-form-${activeTab}`"
        @submit.prevent="save"
      >
        <template v-if="activeTab === 'general'">
          <fieldset class="min-w-0 space-y-4">
            <legend class="text-lg font-medium text-slate-600 dark:text-slate-400">
              {{ t('admin.settings.site_status') }}
            </legend>
            <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/50 dark:bg-amber-950/20">
              <div class="min-w-0">
                <span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">
                  {{ t('admin.settings.maintenance_mode') }}
                </span>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                  {{ t('admin.settings.maintenance_hint') }}
                </p>
              </div>
              <div class="flex min-w-0 items-center gap-3">
                <span
                  class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400"
                  :data-testid="`${props.test_id}-maintenance-status`"
                >
                  {{
                    form.site_maintenance_mode
                      ? t('admin.settings.switch_on')
                      : t('admin.settings.switch_off')
                  }}
                </span>
                <AdminBaseSwitch
                  v-model="form.site_maintenance_mode"
                  :label="t('admin.settings.maintenance_mode')"
                  :test_id="`${props.test_id}-maintenance`"
                />
              </div>
            </div>
          </fieldset>

          <fieldset class="space-y-3">
            <legend class="text-lg font-medium text-slate-600 dark:text-slate-400">
              {{ t('admin.settings.languages') }}
            </legend>
            <div>
              <span class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200">
                {{ t('admin.settings.supported_languages') }}
              </span>
              <div class="flex flex-wrap gap-6">
                <AdminBaseCheckbox
                  v-for="locale in SUPPORTED_LOCALES"
                  :key="locale"
                  :model-value="form.supported_locales.includes(locale)"
                  :label="`${t(`admin.locales.${locale}`)} (${locale})`"
                  :disabled="isLastLocale(locale)"
                  :test_id="`${props.test_id}-locale-${locale}`"
                  @update:model-value="toggleLocale(locale, $event)"
                />
              </div>
            </div>
          </fieldset>

          <fieldset class="space-y-4">
            <legend class="text-lg font-medium text-slate-600 dark:text-slate-400">
              {{ t('admin.settings.content_settings') }}
            </legend>
            <AdminBaseInput
              v-model="form.image_max_dimension"
              type="number"
              min="512"
              max="4096"
              :label="t('admin.settings.image_max_dimension')"
              :help="t('admin.settings.image_max_dimension_hint')"
              :error="fieldError('image_max_dimension')"
              :test_id="`${props.test_id}-image-max-dimension`"
            />
          </fieldset>

          <fieldset class="space-y-4">
            <legend class="text-lg font-medium text-slate-600 dark:text-slate-400">
              {{ t('admin.settings.pagination') }}
            </legend>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <AdminBaseInput
                v-model="form.items_per_page"
                type="number"
                min="1"
                :label="t('admin.settings.admin_items_per_page')"
                :error="fieldError('items_per_page')"
                :test_id="`${props.test_id}-items-per-page`"
              />
              <AdminBaseInput
                v-model="form.projects_per_page"
                type="number"
                min="1"
                :label="t('admin.settings.projects_per_page')"
                :error="fieldError('projects_per_page')"
                :test_id="`${props.test_id}-projects-per-page`"
              />
              <AdminBaseInput
                v-model="form.articles_per_page"
                type="number"
                min="1"
                :label="t('admin.settings.articles_per_page')"
                :error="fieldError('articles_per_page')"
                :test_id="`${props.test_id}-articles-per-page`"
              />
              <AdminBaseInput
                v-model="form.experience_items_per_page"
                type="number"
                min="1"
                :label="t('admin.settings.experience_items_per_page')"
                :error="fieldError('experience_items_per_page')"
                :test_id="`${props.test_id}-experience-per-page`"
              />
            </div>
          </fieldset>

          <fieldset class="space-y-4">
            <legend class="text-lg font-medium text-slate-600 dark:text-slate-400">
              {{ t('admin.settings.data_retention') }}
            </legend>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <AdminBaseInput
                v-model="form.trash_retention_days"
                type="number"
                min="0"
                :label="t('admin.settings.trash_retention_days')"
                :error="fieldError('trash_retention_days')"
                :test_id="`${props.test_id}-trash-days`"
              />
              <AdminBaseInput
                v-model="form.backup_max_count"
                type="number"
                min="1"
                :label="t('admin.settings.backup_max_count')"
                :error="fieldError('backup_max_count')"
                :test_id="`${props.test_id}-backup-max`"
              />
              <AdminBaseInput
                v-model="form.backup_upload_max_size"
                type="number"
                min="1"
                :label="t('admin.settings.backup_upload_max_size')"
                :error="fieldError('backup_upload_max_size')"
                :test_id="`${props.test_id}-backup-size`"
              />
            </div>
          </fieldset>
        </template>

        <template v-else-if="activeTab === 'mail'">
          <fieldset class="space-y-4">
            <legend class="text-lg font-medium text-slate-600 dark:text-slate-400">
              {{ t('admin.settings.api_connection') }}
            </legend>
            <AdminBaseInput
              v-model="form.mail_api_endpoint"
              type="url"
              :label="t('admin.settings.api_endpoint')"
              :error="fieldError('mail_api_endpoint')"
              :placeholder="'https://api.example.com/v1/mail'"
              :test_id="`${props.test_id}-api-endpoint`"
            />
            <AdminBaseInput
              v-model="form.mail_api_token"
              type="password"
              :label="t('admin.settings.api_token')"
              :error="fieldError('mail_api_token')"
              :help="t('admin.settings.api_token_hint')"
              :test_id="`${props.test_id}-api-token`"
            />
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <AdminBaseInput
                v-model="form.mail_from_address"
                type="email"
                :label="t('admin.settings.from_address')"
                :error="fieldError('mail_from_address')"
                :test_id="`${props.test_id}-from-address`"
              />
              <AdminBaseInput
                v-model="form.mail_from_name"
                :label="t('admin.settings.from_name')"
                :error="fieldError('mail_from_name')"
                :test_id="`${props.test_id}-from-name`"
              />
            </div>
            <AdminBaseButton
              type="button"
              variant="secondary"
              :loading="isTesting"
              :test_id="`${props.test_id}-test-connection`"
              @click="testMail"
            >
              {{ t('admin.settings.test_connection') }}
            </AdminBaseButton>
          </fieldset>

          <fieldset class="space-y-3">
            <legend class="text-lg font-medium text-slate-600 dark:text-slate-400">
              {{ t('admin.settings.notifications') }}
            </legend>
            <AdminBaseCheckbox
              v-model="form.email_on_feedback"
              :label="t('admin.settings.email_on_feedback')"
              :test_id="`${props.test_id}-email-on-feedback`"
            />
          </fieldset>
        </template>

        <template v-else>
          <fieldset class="space-y-4">
            <legend class="text-lg font-medium text-slate-600 dark:text-slate-400">
              {{ t('admin.settings.reports') }}
            </legend>
            <AdminBaseCheckbox
              v-model="form.analytics_email_enabled"
              :label="t('admin.settings.email_reports')"
              :test_id="`${props.test_id}-email-reports`"
            />
            <AdminBaseInput
              v-model="form.analytics_email_recipient"
              type="email"
              :label="t('admin.settings.report_recipient_email')"
              :error="fieldError('analytics_email_recipient')"
              :test_id="`${props.test_id}-report-recipient`"
            />
          </fieldset>
        </template>

        <div class="flex items-center justify-end gap-3 pt-2">
          <AdminBaseButton
            type="submit"
            variant="primary"
            :loading="isSaving"
            :test_id="`${props.test_id}-save`"
          >
            {{ t('common.save') }}
          </AdminBaseButton>
        </div>
      </form>

      <!-- Feature Flags tab — separate PATCH endpoint, no Save. -->
      <div
        v-else
        class="space-y-2 rounded-lg border border-slate-200 bg-white p-6 shadow-card dark:border-slate-700 dark:bg-surface-dark-secondary"
        :data-testid="`${props.test_id}-form-flags`"
      >
        <FeatureFlagToggle
          v-for="row in flagRows"
          :key="row.key"
          :flag="row"
          :hint="flagHints[row.key]"
          :test_id="`${props.test_id}-flag-${row.key}`"
          @toggle="onFlagToggle"
        />
      </div>
    </section>
  </AdminPageLayout>
</template>

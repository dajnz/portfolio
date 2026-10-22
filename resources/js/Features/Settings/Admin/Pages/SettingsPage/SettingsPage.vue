<!-- SettingsPage — the tabbed settings form: General, Mail, Feature Flags and Analytics. The complex component's ORCHESTRATOR: tab state lives in `useSettingsTabs`, the form state and per-tab save round-trips in `useSettingsForm`, the flag PATCHes in `useFeatureFlags` and the mail probe in `useMailConnectionTest`; every tab panel renders through its own sub-component. Each form tab submits a grouped `{ settings: { key: value } }` payload to PUT /admin/settings. -->
<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminPageLayout from '@shared-admin/AdminPageLayout.vue';
import { useAdminMenu } from '@shared-cross/Composables/useAdminMenu';
import { useI18n } from '@shared-cross/Composables/useI18n';

import SettingsAnalyticsTab from './SettingsAnalyticsTab.vue';
import SettingsFeatureFlagsTab from './SettingsFeatureFlagsTab.vue';
import SettingsGeneralTab from './SettingsGeneralTab.vue';
import SettingsMailTab from './SettingsMailTab.vue';
import SettingsTabs from './SettingsTabs.vue';
import { useFeatureFlags } from './useFeatureFlags';
import { useMailConnectionTest } from './useMailConnectionTest';
import { useSettingsForm } from './useSettingsForm';
import { SETTINGS_TABS, useSettingsTabs } from './useSettingsTabs';

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

const { t } = useI18n();
const page = usePage<{ locale?: string; featureFlags?: Record<string, boolean> }>();
const { menuGroups } = useAdminMenu();

const { activeTab, selectTab } = useSettingsTabs();

const { form, errors, isSaving, headerLocales, disabledLocales, mailPayload, toggleLocale, save } =
    useSettingsForm({
        settings: () => props.settings,
        activeTab: () => activeTab.value,
    });

const { isTesting, testMail } = useMailConnectionTest(mailPayload);

const { flagRows, flagHints, onFlagToggle } = useFeatureFlags(() => page.props.featureFlags);
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

      <SettingsTabs
        :tabs="SETTINGS_TABS"
        :active_tab="activeTab"
        :test_id="props.test_id"
        @select="selectTab"
      />

      <form
        v-if="activeTab !== 'flags'"
        class="space-y-6 rounded-lg border border-slate-200 bg-white p-6 shadow-card dark:border-slate-700 dark:bg-surface-dark-secondary"
        novalidate
        :data-testid="`${props.test_id}-form-${activeTab}`"
        @submit.prevent="save"
      >
        <SettingsGeneralTab
          v-if="activeTab === 'general'"
          v-model:site_maintenance_mode="form.site_maintenance_mode"
          v-model:image_max_dimension="form.image_max_dimension"
          v-model:items_per_page="form.items_per_page"
          v-model:projects_per_page="form.projects_per_page"
          v-model:articles_per_page="form.articles_per_page"
          v-model:experience_items_per_page="form.experience_items_per_page"
          v-model:trash_retention_days="form.trash_retention_days"
          v-model:backup_max_count="form.backup_max_count"
          v-model:backup_upload_max_size="form.backup_upload_max_size"
          :supported_locales="form.supported_locales"
          :disabled_locales="disabledLocales"
          :errors="errors"
          :test_id="props.test_id"
          @toggle-locale="toggleLocale"
        />

        <SettingsMailTab
          v-else-if="activeTab === 'mail'"
          v-model:mail_api_endpoint="form.mail_api_endpoint"
          v-model:mail_api_token="form.mail_api_token"
          v-model:mail_from_address="form.mail_from_address"
          v-model:mail_from_name="form.mail_from_name"
          v-model:email_on_feedback="form.email_on_feedback"
          :errors="errors"
          :is_testing="isTesting"
          :test_id="props.test_id"
          @test="testMail"
        />

        <SettingsAnalyticsTab
          v-else
          v-model:analytics_email_enabled="form.analytics_email_enabled"
          v-model:analytics_email_recipient="form.analytics_email_recipient"
          :errors="errors"
          :test_id="props.test_id"
        />

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

      <SettingsFeatureFlagsTab
        v-else
        :rows="flagRows"
        :hints="flagHints"
        :test_id="props.test_id"
        @toggle="onFlagToggle"
      />
    </section>
  </AdminPageLayout>
</template>

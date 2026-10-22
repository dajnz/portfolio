<!-- SettingsAnalyticsTab — the Settings page Analytics tab: the report email toggle and its recipient. Purely presentational — values come in as props, edits go out as `update:<field>` events. -->
<script setup lang="ts">
import AdminBaseCheckbox from '@shared-admin/AdminBaseCheckbox.vue';
import AdminBaseInput from '@shared-admin/AdminBaseInput.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';

const props = withDefaults(
    defineProps<{
        analytics_email_enabled: boolean;
        analytics_email_recipient: string;
        errors: Record<string, string>;
        test_id?: string;
    }>(),
    {
        test_id: 'settings-analytics-tab',
    },
);

const emit = defineEmits<{
    'update:analytics_email_enabled': [value: boolean];
    'update:analytics_email_recipient': [value: string];
}>();

const { t } = useI18n();

function fieldError(field: string): string | null {
    return props.errors[`settings.${field}`] ?? null;
}
</script>

<template>
  <fieldset class="space-y-4">
    <legend class="text-lg font-medium text-slate-600 dark:text-slate-400">
      {{ t('admin.settings.reports') }}
    </legend>
    <AdminBaseCheckbox
      :model-value="props.analytics_email_enabled"
      :label="t('admin.settings.email_reports')"
      :test_id="`${props.test_id}-email-reports`"
      @update:model-value="(value) => emit('update:analytics_email_enabled', value)"
    />
    <AdminBaseInput
      :model-value="props.analytics_email_recipient"
      type="email"
      :label="t('admin.settings.report_recipient_email')"
      :error="fieldError('analytics_email_recipient')"
      :test_id="`${props.test_id}-report-recipient`"
      @update:model-value="(value) => emit('update:analytics_email_recipient', value)"
    />
  </fieldset>
</template>

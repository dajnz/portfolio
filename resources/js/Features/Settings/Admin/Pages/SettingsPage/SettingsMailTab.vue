<!-- SettingsMailTab — the Settings page Mail tab: API Connection (endpoint, token, from address/name and the Test Connection probe) plus the Notifications checkbox. Purely presentational — values come in as props, edits go out as `update:<field>` / `test` events. -->
<script setup lang="ts">
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminBaseCheckbox from '@shared-admin/AdminBaseCheckbox.vue';
import AdminBaseInput from '@shared-admin/AdminBaseInput.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';

const props = withDefaults(
    defineProps<{
        mail_api_endpoint: string;
        mail_api_token: string;
        mail_from_address: string;
        mail_from_name: string;
        email_on_feedback: boolean;
        errors: Record<string, string>;
        is_testing: boolean;
        test_id?: string;
    }>(),
    {
        test_id: 'settings-mail-tab',
    },
);

const emit = defineEmits<{
    'update:mail_api_endpoint': [value: string];
    'update:mail_api_token': [value: string];
    'update:mail_from_address': [value: string];
    'update:mail_from_name': [value: string];
    'update:email_on_feedback': [value: boolean];
    test: [];
}>();

const { t } = useI18n();

function fieldError(field: string): string | null {
    return props.errors[`settings.${field}`] ?? null;
}
</script>

<template>
  <fieldset class="space-y-4">
    <legend class="text-lg font-medium text-slate-600 dark:text-slate-400">
      {{ t('admin.settings.api_connection') }}
    </legend>
    <AdminBaseInput
      :model-value="props.mail_api_endpoint"
      type="url"
      :label="t('admin.settings.api_endpoint')"
      :error="fieldError('mail_api_endpoint')"
      :placeholder="'https://api.example.com/v1/mail'"
      :test_id="`${props.test_id}-api-endpoint`"
      @update:model-value="(value) => emit('update:mail_api_endpoint', value)"
    />
    <AdminBaseInput
      :model-value="props.mail_api_token"
      type="password"
      :label="t('admin.settings.api_token')"
      :error="fieldError('mail_api_token')"
      :help="t('admin.settings.api_token_hint')"
      :test_id="`${props.test_id}-api-token`"
      @update:model-value="(value) => emit('update:mail_api_token', value)"
    />
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <AdminBaseInput
        :model-value="props.mail_from_address"
        type="email"
        :label="t('admin.settings.from_address')"
        :error="fieldError('mail_from_address')"
        :test_id="`${props.test_id}-from-address`"
        @update:model-value="(value) => emit('update:mail_from_address', value)"
      />
      <AdminBaseInput
        :model-value="props.mail_from_name"
        :label="t('admin.settings.from_name')"
        :error="fieldError('mail_from_name')"
        :test_id="`${props.test_id}-from-name`"
        @update:model-value="(value) => emit('update:mail_from_name', value)"
      />
    </div>
    <AdminBaseButton
      type="button"
      variant="secondary"
      :loading="props.is_testing"
      :test_id="`${props.test_id}-test-connection`"
      @click="emit('test')"
    >
      {{ t('admin.settings.test_connection') }}
    </AdminBaseButton>
  </fieldset>

  <fieldset class="space-y-3">
    <legend class="text-lg font-medium text-slate-600 dark:text-slate-400">
      {{ t('admin.settings.notifications') }}
    </legend>
    <AdminBaseCheckbox
      :model-value="props.email_on_feedback"
      :label="t('admin.settings.email_on_feedback')"
      :test_id="`${props.test_id}-email-on-feedback`"
      @update:model-value="(value) => emit('update:email_on_feedback', value)"
    />
  </fieldset>
</template>

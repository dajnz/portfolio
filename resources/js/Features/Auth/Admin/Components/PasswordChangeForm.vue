<!-- PasswordChangeForm — password change card of the profile page. Hidden (with an info notice) when the admin's primary login is GitHub OAuth. Emits the password fields; the page merges them with the profile fields for `PUT /admin/profile`. -->
<script setup lang="ts">
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminBaseInput from '@shared-admin/AdminBaseInput.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { ref } from 'vue';

interface PasswordChangePayload {
    current_password: string;
    password: string;
    password_confirmation: string;
}

const props = withDefaults(
    defineProps<{
        is_oauth_primary?: boolean;
        is_submitting?: boolean;
        errors?: Record<string, string>;
        test_id?: string;
    }>(),
    {
        is_oauth_primary: false,
        is_submitting: false,
        errors: () => ({}),
        test_id: 'password-change-form',
    },
);

const emit = defineEmits<{
    submit: [payload: PasswordChangePayload];
}>();

const { t } = useI18n();

const currentPassword = ref('');
const newPassword = ref('');
const newPasswordConfirmation = ref('');

function submitForm(): void {
    if (props.is_submitting) {
        return;
    }

    emit('submit', {
        current_password: currentPassword.value,
        password: newPassword.value,
        password_confirmation: newPasswordConfirmation.value,
    });
}

// Cleared by the page after a successful change.
function reset(): void {
    currentPassword.value = '';
    newPassword.value = '';
    newPasswordConfirmation.value = '';
}

defineExpose({ reset });
</script>

<template>
  <section
    class="rounded-lg border border-slate-200 bg-white p-6 shadow-card dark:border-slate-700 dark:bg-surface-dark-secondary"
    :data-testid="props.test_id"
  >
    <p
      v-if="props.is_oauth_primary"
      class="text-sm text-slate-600 dark:text-slate-400"
      :data-testid="`${props.test_id}-notice`"
    >
      {{ t('admin.profile.oauth_primary_notice') }}
    </p>

    <template v-else>
      <h2 class="mb-4 text-lg font-medium text-slate-600 dark:text-slate-400">
        {{ t('admin.profile.change_password') }}
      </h2>

      <form
        class="space-y-4"
        novalidate
        :data-testid="`${props.test_id}-form`"
        @submit.prevent="submitForm"
      >
        <AdminBaseInput
          v-model="currentPassword"
          name="current_password"
          :label="t('admin.profile.current_password')"
          type="password"
          placeholder="••••••••"
          autocomplete="current-password"
          :error="props.errors.current_password ?? null"
          :disabled="props.is_submitting"
          :required="true"
          :test_id="`${props.test_id}-current-input`"
        />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <AdminBaseInput
            v-model="newPassword"
            name="password"
            :label="t('admin.profile.new_password')"
            type="password"
            placeholder="••••••••"
            autocomplete="new-password"
            :error="props.errors.password ?? null"
            :disabled="props.is_submitting"
            :required="true"
            :test_id="`${props.test_id}-new-input`"
          />
          <AdminBaseInput
            v-model="newPasswordConfirmation"
            name="password_confirmation"
            :label="t('admin.profile.confirm_new_password')"
            type="password"
            placeholder="••••••••"
            autocomplete="new-password"
            :error="props.errors.password_confirmation ?? null"
            :disabled="props.is_submitting"
            :required="true"
            :test_id="`${props.test_id}-confirm-input`"
          />
        </div>

        <div class="flex items-center justify-end">
          <AdminBaseButton
            type="submit"
            variant="secondary"
            :loading="props.is_submitting"
            :test_id="`${props.test_id}-submit-btn`"
          >
            {{ t('admin.profile.change_password') }}
          </AdminBaseButton>
        </div>
      </form>
    </template>
  </section>
</template>

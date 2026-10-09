<!-- TotpVerificationPage — renders LoginCard with the TOTP code form; shared by the login half-auth flow (/admin/login/totp) and the GitHub-link re-verification (/admin/profile/github-link/totp). The form POSTs to the current URL; the github-link variant shows the link-confirmation hint. -->
<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
// biome-ignore lint/style/useImportType: used in the template AND as a type for the focus ref
import AdminBaseInput from '@shared-admin/AdminBaseInput.vue';
import AdminBaseToast from '@shared-admin/AdminBaseToast.vue';
import LanguageSelector from '@shared-cross/Components/LanguageSelector.vue';
import ThemeSwitch from '@shared-cross/Components/ThemeSwitch.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { computed, onMounted, ref } from 'vue';

import LoginCard from '../Components/LoginCard.vue';

const props = withDefaults(
    defineProps<{
        lock_remaining_seconds?: number | null;
        test_id?: string;
    }>(),
    {
        lock_remaining_seconds: null,
        test_id: 'totp-page',
    },
);

const { t } = useI18n();

// The two TOTP routes render the same component; the submission target is
// the page's own URL (login.totp vs profile.github-link.totp).
const submitUrl = usePage().url;
const isGithubLinkFlow = computed(() => submitUrl.includes('/github-link/'));

const code = ref('');
const useRecoveryCode = ref(false);
const isSubmitting = ref(false);
const errorMessage = ref<string | null>(null);
const codeInput = ref<InstanceType<typeof AdminBaseInput> | null>(null);

const hintText = computed(() => {
    if (useRecoveryCode.value) {
        return t('admin.login.enter_recovery_code');
    }

    return isGithubLinkFlow.value
        ? t('admin.profile.enter_totp_to_link')
        : t('admin.login.enter_totp_code');
});

const inputMode = computed(() => (useRecoveryCode.value ? 'text' : 'numeric'));

function toggleMode(): void {
    useRecoveryCode.value = !useRecoveryCode.value;
    errorMessage.value = null;
}

function submitForm(): void {
    if (isSubmitting.value) {
        return;
    }

    isSubmitting.value = true;
    errorMessage.value = null;

    router.post(
        submitUrl,
        { code: code.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                isSubmitting.value = false;
            },
            onError: (serverErrors) => {
                isSubmitting.value = false;
                errorMessage.value = serverErrors.code ?? null;
            },
        },
    );
}

onMounted(() => {
    codeInput.value?.focus();
});

const footerClasses = {
    language: {
        button: 'h-9 rounded-lg border border-slate-200 px-3 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800',
    },
    theme: {
        button: 'inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800',
    },
};
</script>

<template>
  <LoginCard
    :title="t('admin.login.two_factor')"
    :is_loading="isSubmitting"
    :is_github_enabled="false"
    :test_id="props.test_id"
  >
    <form
      class="space-y-4"
      novalidate
      :data-testid="`${props.test_id}-form`"
      @submit.prevent="submitForm"
    >
      <p
        v-if="errorMessage !== null"
        class="rounded-lg border border-danger-200 bg-danger-50 px-3 py-2 text-sm font-medium text-danger-700 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-400"
        role="alert"
        :data-testid="`${props.test_id}-error`"
      >
        {{ errorMessage }}
      </p>

      <p
        class="text-sm text-slate-500 dark:text-slate-400"
        :data-testid="`${props.test_id}-hint`"
      >
        {{ hintText }}
      </p>

      <AdminBaseInput
        ref="codeInput"
        v-model="code"
        name="code"
        :label="t('admin.login.verification_code')"
        type="text"
        :inputmode="inputMode"
        placeholder="000000"
        autocomplete="one-time-code"
        :disabled="isSubmitting"
        :required="true"
        :test_id="`${props.test_id}-code-input`"
      />

      <AdminBaseButton
        type="submit"
        variant="primary"
        class="w-full h-11"
        :loading="isSubmitting"
        :test_id="`${props.test_id}-verify-btn`"
      >
        {{ t('admin.login.verify') }}
      </AdminBaseButton>

      <AdminBaseButton
        type="button"
        variant="ghost"
        class="w-full h-10"
        :disabled="isSubmitting"
        :test_id="`${props.test_id}-toggle-btn`"
        @click="toggleMode"
      >
        {{
          useRecoveryCode
            ? t('admin.login.use_totp_code')
            : t('admin.login.use_recovery_code')
        }}
      </AdminBaseButton>
    </form>

    <template #footer>
      <LanguageSelector
        :classes="footerClasses.language"
        :test_id="`${props.test_id}-language`"
      />
      <ThemeSwitch
        :classes="footerClasses.theme"
        :test_id="`${props.test_id}-theme`"
      />
    </template>
  </LoginCard>
  <!-- Verification errors + lockout warnings surface here as toasts. -->
  <AdminBaseToast />
</template>

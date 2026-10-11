<!-- LoginPage — standalone auth page (no AdminPageLayout). POSTs to /admin/login; validation errors surface inline. -->
<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminBaseCheckbox from '@shared-admin/AdminBaseCheckbox.vue';
import AdminBaseInput from '@shared-admin/AdminBaseInput.vue';
import AdminBaseToast from '@shared-admin/AdminBaseToast.vue';
import LanguageSelector from '@shared-cross/Components/LanguageSelector.vue';
import ThemeSwitch from '@shared-cross/Components/ThemeSwitch.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { ref } from 'vue';

import LoginCard from '../Components/LoginCard.vue';

const props = withDefaults(
    defineProps<{
        github_login_available: boolean;
        loginPage: boolean;
        test_id?: string;
    }>(),
    {
        test_id: 'login-page',
    },
);

const { t } = useI18n();

const login = ref('');
const password = ref('');
const remember = ref(false);
const isSubmitting = ref(false);
const errorMessage = ref<string | null>(null);

const githubButtonClasses = {
    button: 'h-9 rounded-lg border border-slate-200 px-3 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800',
};

const themeButtonClasses = {
    button: 'inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800',
};

function submitForm(): void {
    if (isSubmitting.value) {
        return;
    }

    isSubmitting.value = true;
    errorMessage.value = null;

    router.post(
        '/admin/login',
        {
            login: login.value,
            password: password.value,
            remember: remember.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                // Server redirects (to the TOTP page or the dashboard) —
                // the page navigates away; nothing to reset here.
                isSubmitting.value = false;
            },
            onError: (serverErrors) => {
                isSubmitting.value = false;
                errorMessage.value = serverErrors.login ?? null;
            },
        },
    );
}

function startGithubLogin(): void {
    if (isSubmitting.value) {
        return;
    }

    // The whole card enters the loading state until the browser leaves
    // for GitHub's OAuth page — prevents double-clicks from firing
    // multiple redirects.
    isSubmitting.value = true;
    window.location.href = '/admin/login/github';
}
</script>

<template>
  <LoginCard
    :is_loading="isSubmitting"
    :is_github_enabled="props.github_login_available"
    :test_id="props.test_id"
    @github-login="startGithubLogin"
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

      <AdminBaseInput
        v-model="login"
        name="login"
        :label="t('admin.profile.login')"
        type="text"
        :placeholder="t('admin.login.login_placeholder')"
        autocomplete="username"
        :disabled="isSubmitting"
        :required="true"
        :test_id="`${props.test_id}-login-input`"
      />

      <AdminBaseInput
        v-model="password"
        name="password"
        :label="t('admin.login.password')"
        type="password"
        placeholder="••••••••"
        autocomplete="current-password"
        :disabled="isSubmitting"
        :required="true"
        :test_id="`${props.test_id}-password-input`"
      />

      <AdminBaseCheckbox
        v-model="remember"
        name="remember"
        :label="t('admin.login.remember_me')"
        :disabled="isSubmitting"
        :test_id="`${props.test_id}-remember-checkbox`"
      />

      <AdminBaseButton
        type="submit"
        variant="primary"
        class="w-full h-11 justify-center"
        :loading="isSubmitting"
        :test_id="`${props.test_id}-submit-btn`"
      >
        {{ t('admin.login.title') }}
      </AdminBaseButton>
    </form>

    <template #footer>
      <LanguageSelector
        :classes="githubButtonClasses"
        :test_id="`${props.test_id}-language`"
      />
      <ThemeSwitch
        :classes="themeButtonClasses"
        :test_id="`${props.test_id}-theme`"
      />
    </template>
  </LoginCard>
  <!-- Login errors + redirect flashes surface here as toasts. -->
  <AdminBaseToast />
</template>

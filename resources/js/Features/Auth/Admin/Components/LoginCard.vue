<!-- LoginCard — standalone centered auth card shared by LoginPage and TotpVerificationPage. The `default` slot carries the form, the `footer` slot the theme/language controls. -->
<script setup lang="ts">
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useThemeStore } from '@shared-cross/Stores/themeStore';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        title?: string;
        logo_url?: string;
        is_github_enabled?: boolean;
        is_loading?: boolean;
        test_id?: string;
    }>(),
    {
        title: undefined,
        logo_url: undefined,
        is_github_enabled: false,
        is_loading: false,
        test_id: 'login-card',
    },
);

const emit = defineEmits<{
    submit: [];
    'github-login': [];
}>();

const { t } = useI18n();

// The prepared theme-variant iconic logo (Makefile `images` pipeline) —
// the themeStore is hydrated from the shared `theme` prop and follows
// the ThemeSwitch toggle reactively.
const themeStore = useThemeStore();
const resolvedLogoUrl = computed(
    () => props.logo_url ?? `/images/logo-iconic-${themeStore.theme}.svg`,
);
const hasLogo = computed(() => resolvedLogoUrl.value !== '');
const logoLetter = computed(() =>
    (props.title ?? t('admin.section_title')).charAt(0).toUpperCase(),
);
</script>

<template>
  <div
    class="flex min-h-screen items-center justify-center px-4 py-12"
    :data-testid="`${props.test_id}-container`"
  >
    <div
      class="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-8 shadow-modal dark:border-slate-700 dark:bg-surface-dark-secondary"
      :data-testid="props.test_id"
    >
      <div class="mb-6 text-center">
        <img
          v-if="hasLogo"
          class="mx-auto mb-3 h-16 w-16 rounded-xl"
          :src="resolvedLogoUrl"
          :alt="props.title ?? t('admin.section_title')"
          :data-testid="`${props.test_id}-logo`"
        >
        <span
          v-else
          class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-primary-600 text-xl font-extrabold text-white"
          :data-testid="`${props.test_id}-logo`"
        >
          {{ logoLetter }}
        </span>
        <h1
          class="text-xl font-bold text-slate-900 dark:text-white"
          :data-testid="`${props.test_id}-title`"
        >
          {{ props.title ?? t('admin.section_title') }}
        </h1>
      </div>

      <slot />

      <template v-if="props.is_github_enabled">
        <div class="relative mt-6">
          <div class="absolute inset-0 flex items-center">
            <div class="w-full border-t border-slate-200 dark:border-slate-700" />
          </div>
          <div class="relative flex justify-center text-xs">
            <span class="bg-white px-3 font-medium text-slate-400 dark:bg-surface-dark-secondary dark:text-slate-500">
              {{ t('admin.login.or') }}
            </span>
          </div>
        </div>

        <AdminBaseButton
          variant="secondary"
          class="mt-4 w-full h-11 justify-center"
          :loading="props.is_loading"
          :test_id="`${props.test_id}-github`"
          @click="emit('github-login')"
        >
          <template #icon>
            <!-- Inline octocat mark — simple-icons is not registered, and
                 the self-hosted stance bans runtime Iconify fetches. -->
            <svg
              viewBox="0 0 24 24"
              class="w-5 h-5"
              fill="currentColor"
              aria-hidden="true"
            >
              <path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12" />
            </svg>
          </template>
          {{ t('admin.login.continue_with_github') }}
        </AdminBaseButton>
      </template>

      <div
        v-if="$slots.footer"
        class="mt-6 flex items-center justify-center gap-4"
      >
        <slot name="footer" />
      </div>
    </div>
  </div>
</template>

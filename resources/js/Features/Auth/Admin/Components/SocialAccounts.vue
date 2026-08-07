<!-- SocialAccounts (admin-section-components.md:1515-1532) — linked-accounts card of the profile page. GitHub is the only provider; unlink requires confirmation. -->
<script setup lang="ts">
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import ConfirmationDialog from '@shared-admin/ConfirmationDialog.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { ref } from 'vue';

interface SocialAccount {
    provider: string;
    provider_name: string;
    is_linked: boolean;
    linked_at: string | null;
}

const props = withDefaults(
    defineProps<{
        accounts?: SocialAccount[];
        is_busy?: boolean;
        // The GitHub OAuth flow is feature-flagged — the Link button is
        // hidden while the flag is off (the backend 404s the gated route,
        // FeatureFlagMiddleware).
        can_link?: boolean;
        test_id?: string;
    }>(),
    {
        accounts: () => [],
        is_busy: false,
        can_link: true,
        test_id: 'social-accounts',
    },
);

const emit = defineEmits<{
    link: [payload: { provider: string }];
    unlink: [payload: { provider: string }];
}>();

const { t } = useI18n();

const pendingUnlink = ref<SocialAccount | null>(null);
const confirmOpen = ref(false);

function requestUnlink(account: SocialAccount): void {
    pendingUnlink.value = account;
    confirmOpen.value = true;
}

function confirmUnlink(): void {
    if (pendingUnlink.value === null) {
        return;
    }

    const provider = pendingUnlink.value.provider;
    confirmOpen.value = false;
    pendingUnlink.value = null;
    emit('unlink', { provider });
}
</script>

<template>
  <section
    class="rounded-lg border border-slate-200 bg-white p-6 shadow-card dark:border-slate-700 dark:bg-surface-dark-secondary"
    :data-testid="props.test_id"
  >
    <h2 class="mb-4 text-lg font-medium text-slate-600 dark:text-slate-400">
      {{ t('admin.profile.linked_accounts') }}
    </h2>

    <div
      v-for="account in props.accounts"
      :key="account.provider"
      class="flex items-center gap-4 rounded-lg border border-slate-200 px-4 py-3 dark:border-slate-700"
      :data-testid="`${props.test_id}-account-${account.provider}`"
    >
      <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-900 text-white">
        <!-- Inline octocat mark (mockup sprite) — simple-icons is not
             registered until step 7.2 (B78). -->
        <svg
          viewBox="0 0 24 24"
          class="w-5 h-5"
          fill="currentColor"
          aria-hidden="true"
        >
          <path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12" />
        </svg>
      </span>
      <div class="flex-1">
        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">
          {{ account.provider_name }}
        </p>
        <p
          class="font-mono text-xs text-slate-400 dark:text-slate-500"
          :data-testid="`${props.test_id}-account-${account.provider}-status`"
        >
          {{
            account.is_linked && account.linked_at !== null
              ? t('admin.profile.linked_since', { date: account.linked_at })
              : account.is_linked
                ? t('admin.profile.linked')
                : t('admin.profile.not_linked')
          }}
        </p>
      </div>
      <AdminBaseButton
        v-if="account.is_linked"
        variant="secondary"
        size="sm"
        :disabled="props.is_busy"
        :test_id="`${props.test_id}-account-${account.provider}-unlink-btn`"
        @click="requestUnlink(account)"
      >
        {{ t('admin.profile.unlink') }}
      </AdminBaseButton>
      <AdminBaseButton
        v-else-if="props.can_link"
        variant="secondary"
        size="sm"
        :disabled="props.is_busy"
        :test_id="`${props.test_id}-account-${account.provider}-link-btn`"
        @click="emit('link', { provider: account.provider })"
      >
        {{ t('admin.profile.link_github') }}
      </AdminBaseButton>
    </div>

    <ConfirmationDialog
      v-if="pendingUnlink !== null"
      v-model="confirmOpen"
      :title="t('admin.profile.unlink_github')"
      :message="t('admin.profile.unlink_confirm_message', { provider: pendingUnlink.provider_name })"
      :confirm_label="t('admin.profile.unlink')"
      :test_id="`${props.test_id}-unlink-dialog`"
      @confirm="confirmUnlink"
    />
  </section>
</template>

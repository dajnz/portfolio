<!-- TOTPSetup (admin-section-components.md:1470-1513) — two-factor card of the profile page. States: disabled (Enable button) → setup (QR + manual key + verify) → enabled (recovery codes + disable). The page owns the HTTP calls and passes results back through props. -->
<script setup lang="ts">
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminBaseInput from '@shared-admin/AdminBaseInput.vue';
import AdminBaseModal from '@shared-admin/AdminBaseModal.vue';
import ConfirmationDialog from '@shared-admin/ConfirmationDialog.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { computed, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        totp_enabled: boolean;
        qr_code_svg?: string | null;
        manual_key?: string | null;
        recovery_codes_remaining?: number;
        recovery_codes?: string[];
        is_busy?: boolean;
        test_id?: string;
    }>(),
    {
        qr_code_svg: null,
        manual_key: null,
        recovery_codes_remaining: 0,
        recovery_codes: () => [],
        is_busy: false,
        test_id: 'totp-setup',
    },
);

const emit = defineEmits<{
    'enable-start': [];
    enable: [payload: { code: string }];
    disable: [payload: { password: string }];
    'regenerate-codes': [payload: { password: string }];
    cancel: [];
}>();

const { t } = useI18n();

// Setup step visibility is internal; the QR/manual key arrive via props
// (set by the page after the `enable-start` HTTP call).
const showSetup = ref(false);
const verifyCode = ref('');
const codesModalOpen = ref(false);
const disableDialogOpen = ref(false);
const regeneratePassword = ref('');
const copied = ref(false);

// Server-rendered inline SVG (authentication.md:216-224) — trusted markup.
const qrMarkup = computed(() => props.qr_code_svg ?? '');

// The codes returned by enable/generate are shown once (modal) — watch the
// prop so a fresh response opens the list automatically.
watch(
    () => props.recovery_codes,
    (codes) => {
        if (codes.length > 0) {
            codesModalOpen.value = true;
        }
    },
);

// After a disable the component must fall back to the default disabled
// state — the setup step (`showSetup`) may still be armed from an earlier
// enrollment, which would otherwise render the stale verification form
// instead of the plain "Enable TOTP" button.
watch(
    () => props.totp_enabled,
    (enabled) => {
        if (!enabled) {
            showSetup.value = false;
            verifyCode.value = '';
        }
    },
);

function startSetup(): void {
    showSetup.value = true;
    emit('enable-start');
}

function cancelSetup(): void {
    showSetup.value = false;
    verifyCode.value = '';
    emit('cancel');
}

function verifyEnable(): void {
    if (props.is_busy) {
        return;
    }

    emit('enable', { code: verifyCode.value });
}

function requestDisable(): void {
    disableDialogOpen.value = true;
}

function confirmDisable(payload: { password: string | null }): void {
    // Close + clear immediately — the request outcome is reported via the
    // server notification toast; a stale dialog with the password would
    // otherwise linger after a successful disable.
    disableDialogOpen.value = false;
    emit('disable', { password: payload.password ?? '' });
}

function requestRegenerate(): void {
    if (props.is_busy || regeneratePassword.value.trim() === '') {
        return;
    }

    emit('regenerate-codes', { password: regeneratePassword.value });
}

async function copyCodes(): Promise<void> {
    try {
        await navigator.clipboard.writeText(props.recovery_codes.join('\n'));
        copied.value = true;
        window.setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        // Clipboard access may be unavailable (non-secure context) — the
        // codes stay visible for manual copying.
    }
}
</script>

<template>
  <section
    class="rounded-lg border border-slate-200 bg-white p-6 shadow-card dark:border-slate-700 dark:bg-surface-dark-secondary"
    :data-testid="props.test_id"
  >
    <div class="mb-4 flex items-center gap-2">
      <svg
        viewBox="0 0 24 24"
        class="w-5 h-5 text-primary-600 dark:text-primary-400"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
      >
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
      </svg>
      <h2 class="text-lg font-medium text-slate-600 dark:text-slate-400">
        {{ t('admin.profile.two_factor') }}
      </h2>
    </div>

    <template v-if="props.totp_enabled">
      <h3
        class="mb-1 text-sm font-semibold text-emerald-600 dark:text-emerald-400"
        :data-testid="`${props.test_id}-status`"
      >
        {{ t('admin.profile.totp_enabled') }}
      </h3>
      <p
        class="mb-4 text-sm text-slate-600 dark:text-slate-400"
        :data-testid="`${props.test_id}-remaining`"
      >
        {{ t('admin.profile.recovery_codes_remaining', { count: props.recovery_codes_remaining }) }}
      </p>
      <div class="flex flex-wrap gap-3">
        <AdminBaseButton
          variant="secondary"
          :disabled="props.is_busy"
          :test_id="`${props.test_id}-view-codes-btn`"
          @click="codesModalOpen = true"
        >
          {{ t('admin.profile.view_recovery_codes') }}
        </AdminBaseButton>
        <AdminBaseButton
          variant="danger"
          :disabled="props.is_busy"
          :test_id="`${props.test_id}-disable-btn`"
          @click="requestDisable"
        >
          {{ t('admin.profile.disable_totp') }}
        </AdminBaseButton>
      </div>
    </template>

    <template v-else-if="showSetup">
      <h3
        class="mb-1 text-sm font-semibold text-slate-600 dark:text-slate-400"
        :data-testid="`${props.test_id}-status`"
      >
        {{ t('admin.profile.totp_disabled') }}
      </h3>
      <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
        {{ t('admin.profile.setup_instructions') }}
      </p>

      <div
        v-if="qrMarkup !== ''"
        class="mb-4 flex justify-center rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700"
        :data-testid="`${props.test_id}-qr`"
      >
        <!-- Server-rendered inline SVG (endroid/qr-code) — trusted markup
             produced by the backend (authentication.md:216-224); the
             eslint vue/no-v-html warning is accepted by design. -->
        <!-- eslint-disable-next-line vue/no-v-html -->
        <div v-html="qrMarkup" />
      </div>

      <p
        v-if="props.manual_key !== null && props.manual_key !== ''"
        class="mb-4 text-center"
        :data-testid="`${props.test_id}-manual-key`"
      >
        <code class="rounded bg-slate-100 px-2 py-1 font-mono text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300">
          {{ props.manual_key }}
        </code>
      </p>

      <form
        class="space-y-4"
        novalidate
        :data-testid="`${props.test_id}-setup-form`"
        @submit.prevent="verifyEnable"
      >
        <AdminBaseInput
          v-model="verifyCode"
          name="verification_code"
          :label="t('admin.login.verification_code')"
          type="text"
          inputmode="numeric"
          placeholder="000000"
          autocomplete="one-time-code"
          :disabled="props.is_busy"
          :required="true"
          :test_id="`${props.test_id}-code-input`"
        />
        <div class="flex items-center justify-end gap-3">
          <AdminBaseButton
            type="button"
            variant="ghost"
            :disabled="props.is_busy"
            :test_id="`${props.test_id}-cancel-btn`"
            @click="cancelSetup"
          >
            {{ t('common.cancel') }}
          </AdminBaseButton>
          <AdminBaseButton
            type="submit"
            variant="primary"
            :loading="props.is_busy"
            :test_id="`${props.test_id}-verify-btn`"
          >
            {{ t('admin.profile.verify_enable') }}
          </AdminBaseButton>
        </div>
      </form>
    </template>

    <template v-else>
      <h3
        class="mb-1 text-sm font-semibold text-slate-600 dark:text-slate-400"
        :data-testid="`${props.test_id}-status`"
      >
        {{ t('admin.profile.totp_disabled') }}
      </h3>
      <AdminBaseButton
        variant="secondary"
        class="mt-3"
        :disabled="props.is_busy"
        :test_id="`${props.test_id}-enable-btn`"
        @click="startSetup"
      >
        {{ t('admin.profile.enable_totp') }}
      </AdminBaseButton>
    </template>

    <AdminBaseModal
      v-model="codesModalOpen"
      :title="t('admin.profile.recovery_codes')"
      :test_id="`${props.test_id}-codes-modal`"
    >
      <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
        {{ t('admin.profile.recovery_codes_hint') }}
      </p>
      <ul
        v-if="props.recovery_codes.length > 0"
        class="mb-4 grid grid-cols-1 gap-2"
        :data-testid="`${props.test_id}-codes-list`"
      >
        <li
          v-for="(code, index) in props.recovery_codes"
          :key="code"
          class="rounded-lg bg-slate-100 px-3 py-2 font-mono text-sm text-slate-800 dark:bg-slate-800 dark:text-slate-200"
          :data-testid="`${props.test_id}-code-${index}`"
        >
          {{ code }}
        </li>
      </ul>
      <p
        v-else
        class="mb-4 text-sm text-slate-500 dark:text-slate-400"
        :data-testid="`${props.test_id}-codes-empty`"
      >
        {{ t('admin.profile.no_recovery_codes') }}
      </p>
      <div class="mb-4 flex items-center gap-3">
        <AdminBaseInput
          v-model="regeneratePassword"
          name="current_password"
          :label="t('admin.profile.current_password')"
          type="password"
          placeholder="••••••••"
          autocomplete="current-password"
          :disabled="props.is_busy"
          :test_id="`${props.test_id}-regenerate-password`"
        />
      </div>
      <div class="flex items-center justify-between gap-3">
        <AdminBaseButton
          v-if="props.recovery_codes.length > 0"
          variant="ghost"
          :disabled="props.is_busy"
          :test_id="`${props.test_id}-copy-btn`"
          @click="copyCodes"
        >
          {{
            copied
              ? t('admin.profile.copied')
              : t('admin.profile.copy_to_clipboard')
          }}
        </AdminBaseButton>
        <span v-else />
        <AdminBaseButton
          variant="secondary"
          :disabled="props.is_busy || regeneratePassword.trim() === ''"
          :loading="props.is_busy"
          :test_id="`${props.test_id}-regenerate-btn`"
          @click="requestRegenerate"
        >
          {{ t('admin.profile.generate_new_codes') }}
        </AdminBaseButton>
      </div>
    </AdminBaseModal>

    <ConfirmationDialog
      v-model="disableDialogOpen"
      :title="t('admin.profile.disable_totp')"
      :message="t('admin.profile.disable_totp_confirm')"
      :confirm_label="t('admin.profile.disable_totp')"
      :require_password="true"
      :is_loading="props.is_busy"
      :test_id="`${props.test_id}-disable-dialog`"
      @confirm="confirmDisable"
    />
  </section>
</template>

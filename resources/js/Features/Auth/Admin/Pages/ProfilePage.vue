<!-- ProfilePage (admin-section-components.md:1445-1484, admin-section-ui.md) — admin profile: credentials form, password change, linked GitHub account, TOTP 2FA. Wrapped in AdminPageLayout. -->
<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminBaseInput from '@shared-admin/AdminBaseInput.vue';
import AdminPageLayout from '@shared-admin/AdminPageLayout.vue';
import { useAdminMenu } from '@shared-cross/Composables/useAdminMenu';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useJsonApi } from '@shared-cross/Composables/useJsonApi';
import { useToast } from '@shared-cross/Composables/useToast';
import { computed, ref, watch } from 'vue';

// biome-ignore lint/style/useImportType: used in the template AND as a type for the reset ref
import PasswordChangeForm from '../Components/PasswordChangeForm.vue';
import SocialAccounts from '../Components/SocialAccounts.vue';
import TOTPSetup from '../Components/TOTPSetup.vue';

interface ProfileAdminProps {
    login: string;
    email: string;
    github_linked: boolean;
    totp_enabled: boolean;
    recovery_codes_remaining: number;
    session_version: number;
}

interface SocialAccount {
    provider: string;
    provider_name: string;
    is_linked: boolean;
    linked_at: string | null;
}

interface PasswordChangePayload {
    current_password: string;
    password: string;
    password_confirmation: string;
}

const props = withDefaults(
    defineProps<{
        admin: ProfileAdminProps;
        github_oauth_enabled: boolean;
        test_id?: string;
    }>(),
    {
        test_id: 'profile-page',
    },
);

const { t } = useI18n();
const toast = useToast();
const { post } = useJsonApi();

// The `admins.name` mirrors the login (B56); the page contract does not
// carry it, so the Name field is prefilled from the shared `auth.user`.
const page = usePage<{ auth?: { user?: { name?: string } }; locale?: string }>();
const adminName = computed(() => page.props?.auth?.user?.name ?? props.admin.login);

// ── Profile info form (PUT /admin/profile) ──────────────────────────────
const name = ref(adminName.value);
const email = ref(props.admin.email);
const login = ref(props.admin.login);
const isSavingProfile = ref(false);
const profileErrors = ref<Record<string, string>>({});

// ── Password change (same endpoint, merged payload) ─────────────────────
const passwordForm = ref<InstanceType<typeof PasswordChangeForm> | null>(null);
const isSavingPassword = ref(false);
const passwordErrors = ref<Record<string, string>>({});

// ── TOTP 2FA (JSON endpoints use fetch + XSRF cookie; the redirect-based
//    disable flow goes through Inertia) ──────────────────────────────────
const totpEnabled = ref(props.admin.totp_enabled);
const recoveryCodesRemaining = ref(props.admin.recovery_codes_remaining);
const qrCodeSvg = ref<string | null>(null);
const manualKey = ref<string | null>(null);
const recoveryCodes = ref<string[]>([]);
const totpBusy = ref(false);

// ── Social accounts ─────────────────────────────────────────────────────
const isUnlinking = ref(false);

const socialAccounts = computed<SocialAccount[]>(() => [
    {
        provider: 'github',
        provider_name: t('admin.profile.github'),
        is_linked: props.admin.github_linked,
        // The backend does not expose the link date; the row falls back
        // to a generic "Linked" label (admin-section-components.md:1515-1532).
        linked_at: null,
    },
]);

// Server re-renders refresh the props after every mutation; the local
// state mirrors them so the cards stay in sync without full reloads.
watch(
    () => props.admin,
    (next) => {
        totpEnabled.value = next.totp_enabled;
        recoveryCodesRemaining.value = next.recovery_codes_remaining;
        email.value = next.email;
        login.value = next.login;
        name.value = adminName.value;
    },
);

function submitProfile(): void {
    if (isSavingProfile.value) {
        return;
    }

    isSavingProfile.value = true;
    profileErrors.value = {};

    router.put(
        '/admin/profile',
        { name: name.value, email: email.value, login: login.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                isSavingProfile.value = false;
                profileErrors.value = {};
            },
            onError: (serverErrors) => {
                isSavingProfile.value = false;
                profileErrors.value = { ...serverErrors };
            },
        },
    );
}

function submitPasswordChange(payload: PasswordChangePayload): void {
    if (isSavingPassword.value) {
        return;
    }

    isSavingPassword.value = true;
    passwordErrors.value = {};

    router.put(
        '/admin/profile',
        {
            name: name.value,
            email: email.value,
            login: login.value,
            ...payload,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                isSavingPassword.value = false;
                passwordForm.value?.reset();
                toast.show(t('admin.profile.password_changed'), { variant: 'success' });
            },
            onError: (serverErrors) => {
                isSavingPassword.value = false;
                passwordErrors.value = { ...serverErrors };
            },
        },
    );
}

async function startTotpSetup(): Promise<void> {
    totpBusy.value = true;

    try {
        // The TOTP endpoints return `{ data }` JSON, not Inertia
        // responses — the shared JSON API client handles the CSRF header.
        const body = await post<{ data: Record<string, unknown> }>('/admin/profile/totp-enable');
        const data = body.data as { qr_code_svg?: string; manual_key?: string };

        qrCodeSvg.value = data.qr_code_svg ?? null;
        manualKey.value = data.manual_key ?? null;
    } catch {
        toast.show(t('admin.toasts.failed_to_save'), { variant: 'error' });
    } finally {
        totpBusy.value = false;
    }
}

function cancelTotpSetup(): void {
    qrCodeSvg.value = null;
    manualKey.value = null;
}

async function verifyTotpEnable(payload: { code: string }): Promise<void> {
    totpBusy.value = true;

    try {
        const body = await post<{ data: Record<string, unknown> }>('/admin/profile/totp-enable', {
            json: { code: payload.code },
        });
        const data = body.data as { recovery_codes?: string[] };

        recoveryCodes.value = data.recovery_codes ?? [];
        totpEnabled.value = true;
        recoveryCodesRemaining.value = recoveryCodes.value.length;
    } catch {
        toast.show(t('admin.toasts.failed_to_save'), { variant: 'error' });
    } finally {
        totpBusy.value = false;
    }
}

function disableTotp(payload: { password: string }): void {
    totpBusy.value = true;

    router.post(
        '/admin/profile/totp-disable',
        { current_password: payload.password },
        {
            preserveScroll: true,
            onSuccess: () => {
                totpBusy.value = false;
                qrCodeSvg.value = null;
                manualKey.value = null;
                recoveryCodes.value = [];
            },
            onError: (serverErrors) => {
                totpBusy.value = false;
                if (serverErrors.current_password !== undefined) {
                    toast.show(serverErrors.current_password, { variant: 'error' });
                }
            },
        },
    );
}

async function regenerateRecoveryCodes(payload: { password: string }): Promise<void> {
    totpBusy.value = true;

    try {
        const body = await post<{ data: Record<string, unknown> }>(
            '/admin/profile/totp-recovery-codes',
            {
                json: { current_password: payload.password },
            },
        );
        const data = body.data as { recovery_codes?: string[] };

        recoveryCodes.value = data.recovery_codes ?? [];
        recoveryCodesRemaining.value = recoveryCodes.value.length;
    } catch {
        toast.show(t('admin.toasts.failed_to_save'), { variant: 'error' });
    } finally {
        totpBusy.value = false;
    }
}

function unlinkGithub(): void {
    if (isUnlinking.value) {
        return;
    }

    isUnlinking.value = true;

    router.post('/admin/profile/github-unlink', undefined, {
        preserveScroll: true,
        onFinish: () => {
            isUnlinking.value = false;
        },
    });
}

function linkGithub(): void {
    // OAuth redirect — full page load (Socialite state lives server-side).
    window.location.href = '/admin/profile/github-link';
}

const { menuGroups } = useAdminMenu();
</script>

<template>
  <AdminPageLayout
    :menu_groups="menuGroups()"
    :active_route="'/admin/profile'"
    :admin_name="adminName"
    :logout_url="'/admin/logout'"
    :locale_props="{ active_locale: page.props?.locale ?? 'en', supported_locales: [] }"
    :data-testid="`${props.test_id}-layout`"
  >
    <section
      class="mx-auto max-w-2xl"
      :data-testid="props.test_id"
    >
      <h1 class="mb-6 text-2xl font-bold text-slate-900 dark:text-white">
        {{ t('admin.profile.title') }}
      </h1>

      <!-- ── Profile info form → PUT /admin/profile ──────────────────── -->
      <form
        class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-card dark:border-slate-700 dark:bg-surface-dark-secondary"
        novalidate
        :data-testid="`${props.test_id}-info-form`"
        @submit.prevent="submitProfile"
      >
        <AdminBaseInput
          v-model="name"
          name="name"
          :label="t('admin.profile.name')"
          :error="profileErrors.name ?? null"
          :disabled="isSavingProfile"
          :required="true"
          :maxlength="100"
          :test_id="`${props.test_id}-name-input`"
        />
        <AdminBaseInput
          v-model="email"
          name="email"
          :label="t('admin.profile.email')"
          type="email"
          :error="profileErrors.email ?? null"
          :disabled="isSavingProfile"
          :required="true"
          :test_id="`${props.test_id}-email-input`"
        />
        <AdminBaseInput
          v-model="login"
          name="login"
          :label="t('admin.profile.login')"
          :error="profileErrors.login ?? null"
          :disabled="isSavingProfile"
          :required="true"
          :maxlength="100"
          :test_id="`${props.test_id}-login-input`"
        />
        <div class="flex items-center justify-end">
          <AdminBaseButton
            type="submit"
            variant="primary"
            :loading="isSavingProfile"
            :test_id="`${props.test_id}-save-btn`"
          >
            {{ t('common.save') }}
          </AdminBaseButton>
        </div>
      </form>

      <hr class="my-8 border-slate-200 dark:border-slate-700">

      <PasswordChangeForm
        ref="passwordForm"
        :is_submitting="isSavingPassword"
        :errors="passwordErrors"
        :test_id="`${props.test_id}-password-form`"
        @submit="submitPasswordChange"
      />

      <template v-if="props.github_oauth_enabled">
        <hr class="my-8 border-slate-200 dark:border-slate-700">

        <SocialAccounts
          :accounts="socialAccounts"
          :is_busy="isUnlinking"
          :can_link="props.github_oauth_enabled"
          :test_id="`${props.test_id}-social`"
          @link="linkGithub"
          @unlink="unlinkGithub"
        />
      </template>

      <hr class="my-8 border-slate-200 dark:border-slate-700">

      <TOTPSetup
        :totp_enabled="totpEnabled"
        :qr_code_svg="qrCodeSvg"
        :manual_key="manualKey"
        :recovery_codes_remaining="recoveryCodesRemaining"
        :recovery_codes="recoveryCodes"
        :is_busy="totpBusy"
        :test_id="`${props.test_id}-totp`"
        @enable-start="startTotpSetup"
        @enable="verifyTotpEnable"
        @disable="disableTotp"
        @regenerate-codes="regenerateRecoveryCodes"
        @cancel="cancelTotpSetup"
      />
    </section>
  </AdminPageLayout>
</template>

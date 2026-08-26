<!-- BackupListPage (admin-section-ui.md:468-515, admin-section-components.md:1415-1459, backups.html) — the Backup & Restore admin page: BackupStats, the backups DataTable with pagination, EncryptionKeyList + the show-once KeyGenerationModal, the RestoreForm and the DR UploadRestoreForm. The restore/create progress is polled at ~2 s via the status endpoint (A1); the page picks up in-flight records from the list props (worker-lag fallback: a submitted restore id is polled with a transition requirement). -->
<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import AdminBaseBadge from '@shared-admin/AdminBaseBadge.vue';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminPageLayout from '@shared-admin/AdminPageLayout.vue';
import ConfirmationDialog from '@shared-admin/ConfirmationDialog.vue';
import DataTable from '@shared-admin/DataTable.vue';
import PaginationControls from '@shared-admin/PaginationControls.vue';
import { useAdminMenu } from '@shared-cross/Composables/useAdminMenu';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useJsonApi } from '@shared-cross/Composables/useJsonApi';
import { useToast } from '@shared-cross/Composables/useToast';
import type { CursorNavigationPayload } from '@shared-cross/Utils/cursorNavigationParams';
import { localeOptionsFromCodes } from '@shared-cross/Utils/localeOptions';
import { computed, onMounted, ref, watch } from 'vue';

import BackupStats, { type BackupStatsData } from '../Components/BackupStats.vue';
import EncryptionKeyList, { type EncryptionKeyListItem } from '../Components/EncryptionKeyList.vue';
import KeyGenerationModal from '../Components/KeyGenerationModal.vue';
import RestoreForm, { type RestoreBackupOption } from '../Components/RestoreForm.vue';
import UploadRestoreForm from '../Components/UploadRestoreForm.vue';
import { useBackupStatusPolling } from '../Composables/useBackupStatusPolling';
import type {
    AdminListMeta,
    BackupEntry,
    BackupStatusPayload,
    EncryptionKeyEntry,
    GeneratedKeyResponse,
} from '../Types/backup';
import { formatBackupDate, formatBackupDateTime, formatBytes } from '../Utils/format';

type PollMode = 'create' | 'restore' | 'restore-dr';

const props = withDefaults(
    defineProps<{
        backups?: BackupEntry[];
        meta?: AdminListMeta;
        encryption_key_prompt?: boolean;
        test_id?: string;
    }>(),
    {
        backups: () => [],
        meta: undefined,
        encryption_key_prompt: false,
        test_id: 'backup-list-page',
    },
);

const { t } = useI18n();
const toast = useToast();
const { menuGroups } = useAdminMenu();
const api = useJsonApi();
const page = usePage<{ locale?: string; supported_locales?: string[] }>();

// ── Status polling (create / restore progress, A1) ──────────────────

const polling = useBackupStatusPolling();
const pollMode = ref<PollMode | null>(null);
const restoreError = ref<string | null>(null);
const restoreErrorKind = ref<PollMode | null>(null);

// Template-bound progress: `polling.progress` is a ref nested on a plain
// object — templates only auto-unwrap top-level setup bindings.
const restoreProgress = computed(() => polling.progress.value);

const isCreating = computed(() => pollMode.value === 'create');
const isRestoring = computed(() => pollMode.value === 'restore' || pollMode.value === 'restore-dr');
const restoreKind = computed(() => pollMode.value);

function startCreatePoll(id: number): void {
    pollMode.value = 'create';

    polling.start({
        id,
        onCompleted: () => {
            pollMode.value = null;
            // The completed row (size stamp) lands in the list.
            router.reload();
        },
        onFailed: () => {
            pollMode.value = null;
            router.reload();
        },
        onStopped: () => {
            pollMode.value = null;
        },
    });
}

function startRestorePoll(id: number, kind: PollMode): void {
    pollMode.value = kind;
    restoreError.value = null;
    restoreErrorKind.value = null;

    polling.start({
        id,
        restore: true,
        onCompleted: () => {
            pollMode.value = null;
            // B36 — the success toast fires only after the async restore
            // completes, not at submit time.
            toast.show(
                kind === 'restore-dr'
                    ? t('admin.backups.restore_success')
                    : t('admin.backups.restore_completed'),
                { variant: 'success' },
            );
            router.reload();
        },
        onFailed: (payload: BackupStatusPayload['data']) => {
            pollMode.value = null;
            restoreErrorKind.value = kind;
            restoreError.value = payload.error_message ?? t('admin.backups.restore_failed_generic');
        },
        onStopped: () => {
            pollMode.value = null;
        },
    });
}

/**
 * In-flight detection on every list update: a `pending` row is a
 * running create (the button stays loading), a `processing` row is a
 * running restore (its filename prefix discriminates the DR import).
 */
function detectInFlight(): void {
    if (polling.isPolling.value) {
        return;
    }

    const inFlight = props.backups.find(
        (backup) => backup.status === 'pending' || backup.status === 'processing',
    );

    if (inFlight === undefined) {
        return;
    }

    if (inFlight.status === 'pending') {
        startCreatePoll(inFlight.id);
    } else {
        startRestorePoll(
            inFlight.id,
            inFlight.filename.startsWith('backup-import-') ? 'restore-dr' : 'restore',
        );
    }
}

watch(
    () => props.backups,
    () => detectInFlight(),
    { immediate: true },
);

// ── Backup list (DataTable + pagination) ─────────────────────────────

// A local copy of the rows: the failed restore poll patches the badge
// in place (the inline error must stay visible — no reload).
const tableRows = ref<Array<Record<string, unknown>>>(
    props.backups.map((backup) => ({ ...backup })),
);

watch(
    () => props.backups,
    () => {
        tableRows.value = props.backups.map((backup) => ({ ...backup }));

        // The per-key backup counts (the keys section) must follow the
        // list — a create/delete visit changes the counts even though
        // this component instance survives the Inertia visit.
        void loadKeys();
    },
);

const columns = computed(() => [
    { key: 'date', label: t('admin.backups.date') },
    { key: 'filename', label: t('admin.backups.filename') },
    { key: 'size', label: t('admin.backups.size') },
    { key: 'status', label: t('admin.backups.status') },
]);

function navigate(payload: CursorNavigationPayload): void {
    if (!('page' in payload)) {
        return;
    }

    router.get(
        '/admin/backups',
        { page: payload.page },
        {
            only: ['backups', 'meta'],
            preserveState: true,
            preserveScroll: true,
        },
    );
}

// ── BackupStats (derived from the page props) ────────────────────────

const totalSizeBytes = computed(() =>
    tableRows.value.reduce((sum, backup) => sum + Number(backup.size_bytes), 0),
);

const stats = computed<BackupStatsData>(() => ({
    total_backups: props.meta?.total ?? tableRows.value.length,
    total_size_bytes: totalSizeBytes.value,
    total_size_display: formatBytes(totalSizeBytes.value),
    encryption_active: activeKeyId.value !== null,
    last_backup_date:
        tableRows.value.length > 0
            ? formatBackupDateTime(String(tableRows.value[0].created_at))
            : null,
}));

function createBackup(): void {
    router.post('/admin/backups', undefined, {
        onError: () => {
            toast.show(t('admin.toasts.failed_to_save'), { variant: 'error' });
        },
    });
}

// ── Encryption keys ──────────────────────────────────────────────────

const keys = ref<EncryptionKeyListItem[]>([]);
const activeKeyId = computed(() => keys.value.find((key) => key.is_active)?.id ?? null);

const generateConfirmOpen = ref(false);
const isGenerating = ref(false);
const generatedKey = ref<{ name: string; key_value: string } | null>(null);
const keyModalOpen = ref(false);
const deleteKeyTarget = ref<number | null>(null);
const isDeletingKey = ref(false);

async function loadKeys(): Promise<void> {
    try {
        const response = await api.get<{ data: EncryptionKeyEntry[] }>(
            '/admin/backups/encryption-keys',
        );
        keys.value = response.data.map((key) => ({
            id: key.id,
            name: key.name,
            created_at: key.created_at,
            is_active: key.is_active,
            backup_count: key.backups_count,
        }));
    } catch {
        // The section renders empty on failure — the generate action
        // still works.
    }
}

function openGenerateConfirm(): void {
    if (isGenerating.value) {
        return;
    }

    generateConfirmOpen.value = true;
}

function nextKeyName(): string {
    if (keys.value.length === 0) {
        return 'initial-key';
    }

    const year = new Date().getFullYear();
    const base = `rotated-${year}`;

    if (!keys.value.some((key) => key.name === base)) {
        return base;
    }

    let suffix = 2;
    while (keys.value.some((key) => key.name === `${base}-${suffix}`)) {
        suffix += 1;
    }

    return `${base}-${suffix}`;
}

async function confirmGenerate(): Promise<void> {
    if (isGenerating.value) {
        return;
    }

    isGenerating.value = true;

    try {
        const response = await api.post<GeneratedKeyResponse>('/admin/backups/encryption-keys', {
            json: { name: nextKeyName() },
        });

        generatedKey.value = {
            name: response.data.name,
            key_value: response.data.key_value,
        };
        generateConfirmOpen.value = false;
        keyModalOpen.value = true;
        await loadKeys();
    } catch {
        toast.show(t('admin.toasts.failed_to_save'), { variant: 'error' });
    } finally {
        isGenerating.value = false;
    }
}

async function confirmDeleteKey(): Promise<void> {
    if (deleteKeyTarget.value === null || isDeletingKey.value) {
        return;
    }

    isDeletingKey.value = true;

    try {
        await api.del(`/admin/backups/encryption-keys/${deleteKeyTarget.value}`);
        deleteKeyTarget.value = null;
        await loadKeys();
    } catch {
        toast.show(t('admin.toasts.failed_to_save'), { variant: 'error' });
    } finally {
        isDeletingKey.value = false;
    }
}

// ── Restore forms ────────────────────────────────────────────────────

const restoreOptions = computed<RestoreBackupOption[]>(() =>
    tableRows.value
        .filter((backup) => backup.status === 'completed')
        .map((backup) => ({ id: backup.id as number, filename: backup.filename as string })),
);

const serverErrors = ref<Record<string, string>>({});

function submitRestore(payload: { backup_id: number; password: string }): void {
    serverErrors.value = {};

    router.post('/admin/backups/restore', payload, {
        preserveScroll: true,
        onError: (errors: Record<string, string>) => {
            serverErrors.value = { ...errors };
        },
        onSuccess: () => {
            // The redirect landed; the row may already be `processing`
            // (detected by the props watch) — otherwise poll the
            // submitted record with the transition requirement so a slow
            // worker still surfaces the progress.
            if (!polling.isPolling.value) {
                startRestorePoll(payload.backup_id, 'restore');
            }
        },
    });
}

function submitUploadRestore(payload: {
    file: File;
    encryption_key: string;
    password: string;
}): void {
    serverErrors.value = {};

    const body = new FormData();
    body.append('file', payload.file);
    body.append('key', payload.encryption_key);
    body.append('password', payload.password);

    router.post('/admin/backups/upload', body, {
        preserveScroll: true,
        onError: (errors: Record<string, string>) => {
            serverErrors.value = { ...errors };
        },
        onSuccess: () => {
            // The imported `backup-import-*` row is picked up by the
            // props watch (its id is unknown client-side).
        },
    });
}

function confirmDeleteBackup(): void {
    if (deleteBackupTarget.value === null) {
        return;
    }

    router.delete(`/admin/backups/${deleteBackupTarget.value}`, {
        onFinish: () => {
            deleteBackupTarget.value = null;
        },
    });
}

const deleteBackupTarget = ref<number | null>(null);

// ── Init ─────────────────────────────────────────────────────────────

onMounted(() => {
    void loadKeys();

    // No active key in the DB — the show-once generation prompt
    // (openapi/inertia/backup.yaml `encryption_key_prompt`).
    if (props.encryption_key_prompt) {
        openGenerateConfirm();
    }
});
</script>

<template>
  <AdminPageLayout
    :menu_groups="menuGroups()"
    :active_route="'/admin/backups'"
    :locale_props="{
      active_locale: page.props?.locale ?? 'en',
      supported_locales: localeOptionsFromCodes(page.props?.supported_locales),
    }"
    :data-testid="`${props.test_id}-layout`"
  >
    <section
      class="mx-auto max-w-5xl"
      :data-testid="props.test_id"
    >
      <h1 class="mb-6 text-2xl font-bold text-slate-900 dark:text-white">
        {{ t('admin.backups.title') }}
      </h1>

      <BackupStats
        :stats="stats"
        :active_key_id="activeKeyId"
        :is_creating="isCreating"
        :test_id="`${props.test_id}-stats`"
        @create-backup="createBackup"
      />

      <DataTable
        :columns="columns"
        :rows="tableRows"
        :selected_ids="[]"
        :center_actions="true"
        :empty_mode="'empty'"
        :empty_message="t('admin.backups.no_backups')"
        :test_id="`${props.test_id}-table`"
      >
        <template #cell-date="{ row }">
          <span class="font-mono text-xs text-slate-500 dark:text-slate-400">
            {{ formatBackupDate(String(row.created_at)) }}
          </span>
        </template>
        <template #cell-filename="{ row }">
          <span class="font-mono text-xs text-slate-700 dark:text-slate-200">
            {{ row.filename }}
          </span>
        </template>
        <template #cell-size="{ row }">
          <span class="font-mono text-xs text-slate-500 dark:text-slate-400">
            {{ formatBytes(Number(row.size_bytes)) }}
          </span>
        </template>
        <template #cell-status="{ row }">
          <AdminBaseBadge
            size="sm"
            :variant="
              row.status === 'completed'
                ? 'success'
                : row.status === 'processing'
                  ? 'warning'
                  : row.status === 'failed'
                    ? 'danger'
                    : 'default'
            "
            :test_id="`${props.test_id}-table-status-${String(row.id)}`"
          >
            {{ t(`admin.statuses.${String(row.status)}`) }}
          </AdminBaseBadge>
        </template>
        <template #row-actions="{ row }">
          <div
            class="flex items-center gap-2"
            @click.stop
          >
            <AdminBaseButton
              tag="a"
              :href="`/admin/backups/${String(row.id)}/download`"
              variant="ghost"
              size="sm"
              icon="heroicons:arrow-down-tray"
              :disabled="row.status !== 'completed'"
              :test_id="`${props.test_id}-table-download-${String(row.id)}`"
            >
              {{ t('admin.backups.download') }}
            </AdminBaseButton>
            <AdminBaseButton
              type="button"
              variant="icon-outline-danger"
              icon="heroicons:trash"
              :disabled="row.status === 'processing'"
              :aria-label="t('common.delete')"
              :test_id="`${props.test_id}-table-delete-${String(row.id)}`"
              @click="deleteBackupTarget = Number(row.id)"
            />
          </div>
        </template>
      </DataTable>

      <PaginationControls
        v-if="props.meta !== undefined"
        :total="props.meta.total"
        :per_page="props.meta.per_page"
        :current_page="props.meta.current_page"
        :test_id="`${props.test_id}-pagination`"
        @navigate="navigate"
      />

      <EncryptionKeyList
        :keys="keys"
        :test_id="`${props.test_id}-keys`"
        @generate="openGenerateConfirm"
        @delete="(keyId: number) => (deleteKeyTarget = keyId)"
      />

      <RestoreForm
        :backups="restoreOptions"
        :is_restoring="isRestoring && restoreKind === 'restore'"
        :restore_progress="restoreProgress"
        :restore_error="restoreErrorKind === 'restore' ? restoreError : null"
        :password_error="serverErrors.password ?? null"
        :test_id="`${props.test_id}-restore`"
        @restore="submitRestore"
      />

      <UploadRestoreForm
        :is_restoring="isRestoring && restoreKind === 'restore-dr'"
        :restore_progress="restoreProgress"
        :restore_error="
          serverErrors.file ?? (restoreErrorKind === 'restore-dr' ? restoreError : null)
        "
        :key_error="serverErrors.key ?? null"
        :password_error="serverErrors.password ?? null"
        :test_id="`${props.test_id}-upload`"
        @restore="submitUploadRestore"
      />

      <ConfirmationDialog
        :model-value="generateConfirmOpen"
        :title="t('admin.backups.generate_new_key')"
        :message="t('admin.backups.generate_confirm')"
        :confirm_label="t('common.continue')"
        :is_loading="isGenerating"
        :test_id="`${props.test_id}-generate-dialog`"
        @update:model-value="(open: boolean) => {
          if (!open) generateConfirmOpen = false;
        }"
        @confirm="confirmGenerate"
      />

      <ConfirmationDialog
        :model-value="deleteKeyTarget !== null"
        :title="t('admin.backups.delete_key_confirm')"
        :confirm_label="t('common.delete')"
        :is_loading="isDeletingKey"
        :test_id="`${props.test_id}-delete-key-dialog`"
        @update:model-value="(open: boolean) => {
          if (!open) deleteKeyTarget = null;
        }"
        @confirm="confirmDeleteKey"
      />

      <ConfirmationDialog
        :model-value="deleteBackupTarget !== null"
        :title="t('admin.lists.confirm_delete')"
        :confirm_label="t('common.delete')"
        :test_id="`${props.test_id}-delete-backup-dialog`"
        @update:model-value="(open: boolean) => {
          if (!open) deleteBackupTarget = null;
        }"
        @confirm="confirmDeleteBackup"
      />

      <KeyGenerationModal
        v-model="keyModalOpen"
        :key_value="generatedKey?.key_value ?? ''"
        :key_name="generatedKey?.name ?? ''"
        :test_id="`${props.test_id}-key-modal`"
        @acknowledged="keyModalOpen = false"
      />
    </section>
  </AdminPageLayout>
</template>

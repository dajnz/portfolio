<!-- BackupListPage — the Backup & Restore admin page: BackupStats, the backups DataTable with pagination, EncryptionKeyList + the show-once KeyGenerationModal, the RestoreForm and the DR UploadRestoreForm. A directory-based complex component: this .vue file only wires state and handlers into the layout — the backups-table section (rows/stats/pagination/delete) lives in `useBackupList`, the encryption-keys section (list/generation/delete dialogs) in `useEncryptionKeys`, the create/restore/disaster-recovery operations (submits, the ~2 s status polling, in-flight detection, completion toasts) in `useBackupOperations`; the shared progress bar + finish toasts follow the worker-lag fallback (a submitted restore id / the newest imported row is polled with a transition requirement). -->
<script setup lang="ts">
import { useBackupFormat } from '@backup/Admin/Composables/useBackupFormat';
import type { AdminListMeta, BackupEntry } from '@backup/Admin/Types/backup';
import BackupStats from '@backup-admin/BackupStats.vue';
import EncryptionKeyList from '@backup-admin/EncryptionKeyList.vue';
import KeyGenerationModal from '@backup-admin/KeyGenerationModal.vue';
import RestoreForm from '@backup-admin/RestoreForm.vue';
import UploadRestoreForm from '@backup-admin/UploadRestoreForm.vue';
import { usePage } from '@inertiajs/vue3';
import AdminBaseBadge from '@shared-admin/AdminBaseBadge.vue';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminPageLayout from '@shared-admin/AdminPageLayout.vue';
import ConfirmationDialog from '@shared-admin/ConfirmationDialog.vue';
import DataTable from '@shared-admin/DataTable.vue';
import PaginationControls from '@shared-admin/PaginationControls.vue';
import { useAdminMenu } from '@shared-cross/Composables/useAdminMenu';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { localeOptionsFromCodes } from '@shared-cross/Utils/localeOptions';
import { onMounted, watch } from 'vue';
import { useBackupList } from './useBackupList';
import { useBackupOperations } from './useBackupOperations';
import { useEncryptionKeys } from './useEncryptionKeys';

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
const { menuGroups } = useAdminMenu();
const page = usePage<{ locale?: string; supported_locales?: string[] }>();
const { formatBackupDate, formatBytes } = useBackupFormat();

// The feature sections — each composable owns its state; this file only
// composes them and feeds the template.
const {
    keys,
    activeKeyId,
    generateConfirmOpen,
    isGenerating,
    generatedKey,
    keyModalOpen,
    deleteKeyTarget,
    isDeletingKey,
    loadKeys,
    openGenerateConfirm,
    dismissGenerateConfirm,
    confirmGenerate,
    requestDeleteKey,
    dismissDeleteKey,
    confirmDeleteKey,
} = useEncryptionKeys();

const {
    tableRows,
    deleteBackupTarget,
    columns,
    stats,
    restoreOptions,
    syncRows,
    markRowCompleted,
    navigate,
    requestDeleteBackup,
    dismissDeleteBackup,
    confirmDeleteBackup,
} = useBackupList(props, activeKeyId);

const {
    isCreating,
    restoreInProgress,
    disasterRestoreInProgress,
    restoreProgress,
    restoreErrorMessage,
    disasterRestoreErrorMessage,
    serverErrors,
    disasterRestoreSuccessTick,
    createBackup,
    submitRestore,
    submitUploadRestore,
    detectInFlight,
} = useBackupOperations(props, {
    getStoredKeyCount: () => keys.value.length,
    markRowCompleted,
});

// Every list update: pick an in-flight record up (pending → create poll,
// processing → restore poll), mirror the rows into the local copy and
// refresh the per-key counts (the component survives the Inertia visits,
// so the section state must follow the props).
watch(
    () => props.backups,
    () => {
        detectInFlight();
        syncRows();
        void loadKeys();
    },
    { immediate: true },
);

onMounted(() => {
    // No active key in the DB — the show-once generation prompt
    // (`encryption_key_prompt` prop).
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
              @click="requestDeleteBackup(Number(row.id))"
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
        @delete="requestDeleteKey"
      />

      <RestoreForm
        :backups="restoreOptions"
        :is_restoring="restoreInProgress"
        :restore_progress="restoreProgress"
        :restore_error="restoreErrorMessage"
        :password_error="serverErrors.password ?? null"
        :test_id="`${props.test_id}-restore`"
        @restore="submitRestore"
      />

      <UploadRestoreForm
        :is_restoring="disasterRestoreInProgress"
        :restore_progress="restoreProgress"
        :restore_error="disasterRestoreErrorMessage"
        :key_error="serverErrors.key ?? null"
        :password_error="serverErrors.password ?? null"
        :restore_success_tick="disasterRestoreSuccessTick"
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
          if (!open) dismissGenerateConfirm();
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
          if (!open) dismissDeleteKey();
        }"
        @confirm="confirmDeleteKey"
      />

      <ConfirmationDialog
        :model-value="deleteBackupTarget !== null"
        :title="t('admin.lists.confirm_delete')"
        :confirm_label="t('common.delete')"
        :test_id="`${props.test_id}-delete-backup-dialog`"
        @update:model-value="(open: boolean) => {
          if (!open) dismissDeleteBackup();
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

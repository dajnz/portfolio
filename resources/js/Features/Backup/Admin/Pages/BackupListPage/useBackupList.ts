// BackupListPage — the backups-table section state (BackupListPage.vue
// is the orchestrator). Owns the local copy of the list rows (synced
// from the Inertia props; restore-mode polls patch a re-completed row in
// place), the table columns + pagination navigation, the derived
// BackupStats payload and the completed-row restore options for
// RestoreForm. The deletion of a listed backup (ConfirmationDialog) also
// lives here. The active-key ref comes from `useEncryptionKeys` — the
// stats' encryption line needs it.

import { useBackupFormat } from '@backup/Admin/Composables/useBackupFormat';
import type { AdminListMeta, BackupEntry } from '@backup/Admin/Types/backup';
import type { BackupStatsData } from '@backup-admin/BackupStats.vue';
import type { RestoreBackupOption } from '@backup-admin/RestoreForm.vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from '@shared-cross/Composables/useI18n';
import type { CursorNavigationPayload } from '@shared-cross/Utils/cursorNavigationParams';
import { type ComputedRef, computed, type Ref, ref } from 'vue';

export interface BackupListSource {
    backups: BackupEntry[];
    meta?: AdminListMeta | undefined;
}

export function useBackupList(
    source: BackupListSource,
    activeKeyId: Ref<number | null>,
): {
    tableRows: Ref<Array<Record<string, unknown>>>;
    deleteBackupTarget: Ref<number | null>;
    columns: ComputedRef<Array<{ key: string; label: string }>>;
    stats: ComputedRef<BackupStatsData>;
    restoreOptions: ComputedRef<RestoreBackupOption[]>;
    syncRows: () => void;
    markRowCompleted: (id: number) => void;
    navigate: (payload: CursorNavigationPayload) => void;
    requestDeleteBackup: (backupId: number) => void;
    dismissDeleteBackup: () => void;
    confirmDeleteBackup: () => void;
} {
    const { t } = useI18n();
    const { formatBackupDateTime, formatBytes } = useBackupFormat();

    // A local copy of the rows: the failed restore poll keeps the inline
    // error visible (no reload) and the successful poll patches the
    // re-completed row in place.
    const tableRows = ref<Array<Record<string, unknown>>>(
        source.backups.map((backup) => ({ ...backup })),
    );

    const deleteBackupTarget = ref<number | null>(null);

    const columns = computed(() => [
        { key: 'date', label: t('admin.backups.date') },
        { key: 'filename', label: t('admin.backups.filename') },
        { key: 'size', label: t('admin.backups.size') },
        { key: 'status', label: t('admin.backups.status') },
    ]);

    const totalSizeBytes = computed(() =>
        tableRows.value.reduce((sum, backup) => sum + Number(backup.size_bytes), 0),
    );

    const stats = computed<BackupStatsData>(() => ({
        total_backups: source.meta?.total ?? tableRows.value.length,
        total_size_bytes: totalSizeBytes.value,
        total_size_display: formatBytes(totalSizeBytes.value),
        encryption_active: activeKeyId.value !== null,
        last_backup_date:
            tableRows.value.length > 0
                ? formatBackupDateTime(String(tableRows.value[0].created_at))
                : null,
    }));

    // The RestoreForm dropdown lists only the completed rows.
    const restoreOptions = computed<RestoreBackupOption[]>(() =>
        tableRows.value
            .filter((backup) => backup.status === 'completed')
            .map((backup) => ({ id: backup.id as number, filename: backup.filename as string })),
    );

    function syncRows(): void {
        tableRows.value = source.backups.map((backup) => ({ ...backup }));
    }

    /** A restore-mode poll ended without a reload (the completion toast
     *  must survive) — the patched row keeps the badge truthful until
     *  the next server visit. */
    function markRowCompleted(id: number): void {
        const row = tableRows.value.find((backup) => Number(backup.id) === id);

        if (row !== undefined) {
            row.status = 'completed';
        }
    }

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

    function requestDeleteBackup(backupId: number): void {
        deleteBackupTarget.value = backupId;
    }

    function dismissDeleteBackup(): void {
        deleteBackupTarget.value = null;
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

    return {
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
    };
}

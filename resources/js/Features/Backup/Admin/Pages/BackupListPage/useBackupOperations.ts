// BackupListPage — the backup operations (BackupListPage.vue is the
// orchestrator): the "Create Backup Now" submit, the same-instance
// restore submit (RestoreForm) and the disaster-recovery upload submit
// (UploadRestoreForm), each feeding the ~2 s status-polling loop
// (useBackupStatusPolling). The page picks in-flight records up from the
// list props; on submit the page falls back to polling the record with a
// restore-mode transition requirement (worker-lag — a slow worker leaves
// the row `completed`). Completion handlers show the toasts and patch
// the re-completed row (no reload — a toast shown right before a reload
// is cleared by the visit start, app.vue). The disaster-recovery restore
// additionally owns the stored-key-count baseline (an import adds exactly
// one key row — the finish toast differentiates import from reuse) and
// the success tick the UploadRestoreForm clears its inputs on.

import { useBackupStatusPolling } from '@backup/Admin/Composables/useBackupStatusPolling';
import type { BackupEntry, BackupStatusPayload } from '@backup/Admin/Types/backup';
import { router } from '@inertiajs/vue3';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useToast } from '@shared-cross/Composables/useToast';
import { type ComputedRef, computed, type Ref, ref } from 'vue';

/** The disaster-recovery restore kind of the polling state machine. */
const DISASTER_RECOVERY_RESTORE = 'restore-dr';

type PollMode = 'create' | 'restore' | typeof DISASTER_RECOVERY_RESTORE;

interface BackupOperationsSource {
    backups: BackupEntry[];
}

interface BackupOperationsDependencies {
    /** The current count of stored encryption keys (the keys section
     *  list) — the disaster-recovery import-vs-reuse decision. */
    getStoredKeyCount: () => number;
    /** Patches the locally displayed row after a restore-mode poll
     *  completes (the row re-completed server-side). */
    markRowCompleted: (id: number) => void;
}

export function useBackupOperations(
    source: BackupOperationsSource,
    dependencies: BackupOperationsDependencies,
): {
    isCreating: ComputedRef<boolean>;
    restoreInProgress: ComputedRef<boolean>;
    disasterRestoreInProgress: ComputedRef<boolean>;
    restoreProgress: ComputedRef<number | null>;
    restoreErrorMessage: ComputedRef<string | null>;
    disasterRestoreErrorMessage: ComputedRef<string | null>;
    serverErrors: Ref<Record<string, string>>;
    disasterRestoreSuccessTick: Ref<number>;
    createBackup: () => void;
    submitRestore: (payload: { backup_id: number; password: string }) => void;
    submitUploadRestore: (payload: {
        file: File;
        encryption_key: string;
        password: string;
    }) => void;
    detectInFlight: () => void;
} {
    const { t } = useI18n();
    const toast = useToast();
    const polling = useBackupStatusPolling();

    const pollMode = ref<PollMode | null>(null);
    const restoreError = ref<string | null>(null);
    const restoreErrorKind = ref<PollMode | null>(null);
    const serverErrors = ref<Record<string, string>>({});

    /** The stored-key count snapshot BEFORE a disaster-recovery upload:
     *  an import adds exactly one `disaster-key-*` row, a reuse adds none
     *  — the finish toast differentiates the two cases via the diff.
     *  Null when no disaster-recovery upload was submitted in this page
     *  lifetime. */
    const disasterKeyBaseline = ref<number | null>(null);

    /** Incremented on every successful disaster-recovery restore — the
     *  UploadRestoreForm clears its inputs when the tick changes (success
     *  only). */
    const disasterRestoreSuccessTick = ref(0);

    const isCreating = computed(() => pollMode.value === 'create');
    const restoreInProgress = computed(() => pollMode.value === 'restore');
    const disasterRestoreInProgress = computed(() => pollMode.value === DISASTER_RECOVERY_RESTORE);

    // Template-bound progress: `polling.progress` is a ref nested on a
    // plain object — templates only auto-unwrap top-level setup
    // bindings, so the composables return the unwrapped computed.
    const restoreProgress = computed(() => polling.progress.value);

    // The per-form error slots: the same-instance form shows the poll
    // error only; the disaster-recovery form shows the upload's `file`
    // validation error first (it lands in the dropzone slot), then the
    // poll error.
    const restoreErrorMessage = computed(() =>
        restoreErrorKind.value === 'restore' ? restoreError.value : null,
    );
    const disasterRestoreErrorMessage = computed(() =>
        serverErrors.value.file !== undefined
            ? serverErrors.value.file
            : restoreErrorKind.value === DISASTER_RECOVERY_RESTORE
              ? restoreError.value
              : null,
    );

    function startCreatePoll(id: number): void {
        pollMode.value = 'create';

        polling.start({
            id,
            onCompleted: () => {
                pollMode.value = null;
                // The completed row (size stamp) lands in the list; the
                // "Backup created." toast fires only after the reload
                // landed (the toast queue is cleared when a visit starts,
                // app.vue).
                router.reload({
                    onSuccess: () => {
                        toast.show(t('admin.toasts.backup_created'), { variant: 'success' });
                    },
                });
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

                // The success toast fires only after the async restore
                // completes, not at submit time. No reload here: the
                // restored row re-completed with the archive stamp, so
                // the local copy is patched in place (a toast shown right
                // before a reload would be cleared by the visit start,
                // app.vue).
                dependencies.markRowCompleted(id);

                if (kind === DISASTER_RECOVERY_RESTORE) {
                    // The uploaded key either matched a stored key
                    // (reuse: no new row) or was imported (the count grew
                    // by one). Without a submit snapshot (a restore
                    // started elsewhere, e.g. the CLI) the import message
                    // is the default — the fresh-instance semantics.
                    const imported =
                        disasterKeyBaseline.value === null ||
                        dependencies.getStoredKeyCount() > disasterKeyBaseline.value;

                    disasterKeyBaseline.value = null;
                    disasterRestoreSuccessTick.value += 1;

                    toast.show(
                        imported
                            ? t('admin.backups.restore_success')
                            : t('admin.backups.restore_reused_key'),
                        { variant: 'success' },
                    );

                    return;
                }

                toast.show(t('admin.backups.restore_completed'), { variant: 'success' });
            },
            onFailed: (payload: BackupStatusPayload['data']) => {
                pollMode.value = null;
                restoreErrorKind.value = kind;
                restoreError.value =
                    payload.error_message ?? t('admin.backups.restore_failed_generic');

                if (kind === DISASTER_RECOVERY_RESTORE) {
                    disasterKeyBaseline.value = null;
                }
            },
            onStopped: () => {
                pollMode.value = null;

                if (kind === DISASTER_RECOVERY_RESTORE) {
                    disasterKeyBaseline.value = null;
                }
            },
        });
    }

    /** In-flight detection on every list update: a `pending` row is a
     *  running create (the button stays loading), a `processing` row is
     *  a running restore (its filename prefix discriminates the imported
     *  disaster-recovery archive). */
    function detectInFlight(): void {
        if (polling.isPolling.value) {
            return;
        }

        const inFlight = source.backups.find(
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
                inFlight.filename.startsWith('backup-import-')
                    ? DISASTER_RECOVERY_RESTORE
                    : 'restore',
            );
        }
    }

    function createBackup(): void {
        router.post('/admin/backups', undefined, {
            onError: () => {
                toast.show(t('admin.toasts.failed_to_save'), { variant: 'error' });
            },
        });
    }

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
                // submitted record with the transition requirement so a
                // slow worker still surfaces the progress.
                if (!polling.isPolling.value) {
                    startRestorePoll(payload.backup_id, 'restore');
                }
            },
        });
    }

    /**
     * The just-imported disaster-recovery row: the import ran
     * synchronously during the upload request (before the redirect), so
     * among the `backup-import-*` rows of the current props the newest
     * is this upload's record — its id is unknown client-side, hence
     * the scan.
     */
    function newestImportedBackupId(): number | null {
        let newest: BackupEntry | null = null;

        for (const backup of source.backups) {
            if (!backup.filename.startsWith('backup-import-')) {
                continue;
            }

            if (newest === null || backup.created_at > newest.created_at) {
                newest = backup;
            }
        }

        return newest?.id ?? null;
    }

    function submitUploadRestore(payload: {
        file: File;
        encryption_key: string;
        password: string;
    }): void {
        serverErrors.value = {};

        // Snapshot the stored-key count BEFORE the upload: the finish
        // toast compares it against the list after the restore — an
        // import added one key row, a reuse added none.
        disasterKeyBaseline.value = dependencies.getStoredKeyCount();

        const body = new FormData();
        body.append('file', payload.file);
        body.append('key', payload.encryption_key);
        body.append('password', payload.password);

        router.post('/admin/backups/upload', body, {
            preserveScroll: true,
            onError: (errors: Record<string, string>) => {
                serverErrors.value = { ...errors };
                disasterKeyBaseline.value = null;
            },
            onSuccess: () => {
                // The props watch picks the imported `backup-import-*`
                // row up when the worker already marked it `processing`;
                // with a slow worker the row still reads `completed`, so
                // the imported record is polled with the restore-mode
                // transition requirement — the RestoreForm fallback
                // pattern (otherwise no progress bar / finish toast
                // would ever appear).
                if (!polling.isPolling.value) {
                    const importedId = newestImportedBackupId();

                    if (importedId !== null) {
                        startRestorePoll(importedId, DISASTER_RECOVERY_RESTORE);
                    } else {
                        // No pollable row — nothing will consume the
                        // snapshot.
                        disasterKeyBaseline.value = null;
                    }
                }
            },
        });
    }

    return {
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
    };
}

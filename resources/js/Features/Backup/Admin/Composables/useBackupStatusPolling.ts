import { onBeforeUnmount, type Ref, ref } from 'vue';

import type { BackupStatusPayload } from '../Types/backup';

export interface BackupPollingHandle {
    /** True while the polling loop is active. */
    isPolling: Ref<boolean>;
    /** The last observed progress percentage (`null` = indeterminate). */
    progress: Ref<number | null>;
    /** The `error_message` of a failed backup (null until failure). */
    errorMessage: Ref<string | null>;
    start: (options: BackupPollingOptions) => void;
    stop: () => void;
}

export interface BackupPollingOptions {
    /** The backup record whose status endpoint is polled. */
    id: number;
    /** Restore-mode semantics: a terminal `completed` counts only after
     *  the restore actually ran — either a non-terminal status was
     *  observed (the record re-completes after its restore) or the
     *  record's `completed_at` stamp CHANGED between polls (a worker
     *  faster than the poll interval never lets the record show
     *  `processing`, but the re-completion re-stamps the timestamp). A
     *  404 — the restored database swapped the row out — also counts as
     *  completed. Create-mode polls plain `pending → processing →
     *  completed/failed` and stop silently on a 404. */
    restore?: boolean;
    /** Poll interval in milliseconds (~2 s). */
    intervalMs?: number;
    /** Hard cap on poll attempts (stops silently at the cap). */
    maxTicks?: number;
    onCompleted?: (payload: BackupStatusPayload['data']) => void;
    onFailed?: (payload: BackupStatusPayload['data']) => void;
    /** Fired when the loop ends without a terminal result (404 in
     *  create mode, max ticks, unmount). */
    onStopped?: () => void;
}

const DEFAULT_INTERVAL_MS = 2000;
const DEFAULT_MAX_TICKS = 150;

/**
 * The restore/create progress polling loop (SSE is an explicit
 * non-goal, `GET /admin/backups/{backup}/status` is polled at ~2 s while
 * the site is in maintenance mode). Plain fetch with same-origin
 * credentials: the endpoint sits behind the admin auth chain and GET
 * requests carry no CSRF token. Polls that hit a transport error keep
 * going (the database is mid-restore and may 500), a 404 stops the loop
 * (restore mode: the row was swapped out — completed; create mode: the
 * row vanished — silent stop).
 */
export function useBackupStatusPolling(): BackupPollingHandle {
    const isPolling = ref(false);
    const progress = ref<number | null>(null);
    const errorMessage = ref<string | null>(null);

    let interval: ReturnType<typeof setInterval> | null = null;
    let ticks = 0;
    let observedNonTerminal = false;
    let firstCompletedAt: string | null = null;
    let options: BackupPollingOptions | null = null;

    function stop(): void {
        if (interval !== null) {
            clearInterval(interval);
            interval = null;
        }

        options = null;
        isPolling.value = false;
    }

    function complete(payload: BackupStatusPayload['data']): void {
        const callback = options?.onCompleted;

        stop();
        callback?.(payload);
    }

    function fail(payload: BackupStatusPayload['data']): void {
        const callback = options?.onFailed;

        stop();
        callback?.(payload);
    }

    function pollOnce(): void {
        if (options === null) {
            return;
        }

        const current = options;
        const url = `/admin/backups/${current.id}/status`;

        fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then(async (response) => {
                if (response.status === 404) {
                    if (current.restore === true) {
                        // The restored database no longer contains the
                        // polled record — the restore finished.
                        complete({
                            id: current.id,
                            status: 'completed',
                            progress: null,
                            completed_at: null,
                            error_message: null,
                        });
                    } else {
                        stop();
                        current.onStopped?.();
                    }

                    return;
                }

                if (!response.ok) {
                    // Transient failure (maintenance mode / mid-restore
                    // database) — keep polling until the cap.
                    return;
                }

                const payload = (await response.json()) as BackupStatusPayload;
                const data = payload.data;

                if (data.status === 'processing' || data.status === 'pending') {
                    observedNonTerminal = true;
                    progress.value = data.progress;
                    errorMessage.value = null;

                    return;
                }

                if (data.status === 'completed') {
                    if (current.restore === true && !observedNonTerminal) {
                        if (firstCompletedAt === null) {
                            // First observation — remember the stamp; the
                            // worker may not have picked the record up yet.
                            firstCompletedAt = data.completed_at;

                            return;
                        }

                        if (data.completed_at !== firstCompletedAt) {
                            // The stamp changed — the restore re-completed
                            // the record between polls (a worker faster
                            // than the interval never showed `processing`).
                            complete(data);

                            return;
                        }

                        // Unchanged — the worker has not started; keep
                        // polling.
                        return;
                    }

                    complete(data);
                } else if (data.status === 'failed') {
                    errorMessage.value = data.error_message;
                    fail(data);
                }
            })
            .catch(() => {
                // Network error (site in maintenance, connection reset) —
                // keep polling until the cap.
            })
            .finally(() => {
                ticks += 1;

                if (ticks >= (current.maxTicks ?? DEFAULT_MAX_TICKS)) {
                    stop();
                    current.onStopped?.();
                }
            });
    }

    function start(next: BackupPollingOptions): void {
        stop();

        options = {
            intervalMs: DEFAULT_INTERVAL_MS,
            maxTicks: DEFAULT_MAX_TICKS,
            ...next,
        };
        ticks = 0;
        observedNonTerminal = false;
        firstCompletedAt = null;
        progress.value = null;
        errorMessage.value = null;
        isPolling.value = true;

        pollOnce();
        interval = setInterval(pollOnce, options.intervalMs);
    }

    onBeforeUnmount(() => {
        stop();
    });

    return { isPolling, progress, errorMessage, start, stop };
}

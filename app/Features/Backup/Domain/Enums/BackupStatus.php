<?php

declare(strict_types=1);

namespace App\Features\Backup\Domain\Enums;

/**
 * The backup lifecycle state (backup.md:151-159, database.md:580):
 * `pending` → `processing` → `completed` when the job finishes, or
 * `pending | processing` → `failed` on error. Downloads are only
 * available on `completed`; deletion is blocked while `processing`.
 * The same lifecycle tracks async restores (A1) — the restore job
 * marks the record `processing` and finishes it `completed`/`failed`.
 */
enum BackupStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}

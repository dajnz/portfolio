<?php

declare(strict_types=1);

namespace App\Features\Backup\App\DTOs;

use App\Features\Backup\Domain\Enums\BackupStatus;
use DateTimeImmutable;

/**
 * The status-polling facts of one backup — the `GET
 * /admin/backups/{backup}/status` payload. Immutable.
 */
final readonly class BackupStatusDto
{
    public function __construct(
        public int $id,
        public BackupStatus $status,
        public ?int $progress,
        public ?DateTimeImmutable $completedAt,
        public ?string $errorMessage,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Features\Backup\App\DTOs;

use DateTimeImmutable;

/**
 * One encryption key row of the admin keys list
 * (admin-section-ui.md:482-489): the key's identity, the active flag
 * and how many backups reference it — the "N backups reference this
 * key" tooltip data (backup.md:589) and the delete-button enablement.
 */
final readonly class EncryptionKeyListItemDto
{
    public function __construct(
        public int $id,
        public string $name,
        public DateTimeImmutable $createdAt,
        public bool $isActive,
        public int $backupsCount,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Features\Backup\App\DTOs;

use App\Features\Backup\Domain\Entities\Backup;

/**
 * The backup list page facts: the paginated non-trashed backups
 * (newest first) plus the length-aware meta bounds and the
 * `encryption_key_prompt` flag (no active key in the DB — the modal
 * needs to show). The controller maps the entities onto the wire
 * `BackupEntryData` shape.
 */
final readonly class BackupListDto
{
    /**
     * @param  list<Backup>  $backups
     */
    public function __construct(
        public array $backups,
        public int $currentPage,
        public ?int $from,
        public ?int $to,
        public int $perPage,
        public int $lastPage,
        public int $total,
        public bool $encryptionKeyPrompt,
    ) {
    }
}

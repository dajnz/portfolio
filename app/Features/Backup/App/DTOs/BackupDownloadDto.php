<?php

declare(strict_types=1);

namespace App\Features\Backup\App\DTOs;

/**
 * The download target of one backup: the RustFS object key to stream and
 * the record's display filename for the response header. Immutable.
 */
final readonly class BackupDownloadDto
{
    public function __construct(
        public string $path,
        public string $filename,
    ) {
    }
}

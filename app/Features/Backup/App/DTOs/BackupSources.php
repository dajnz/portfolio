<?php

declare(strict_types=1);

namespace App\Features\Backup\App\DTOs;

/**
 * The workflow configuration bundle (backup.md:391,474): the local
 * staging base (`storage/app/backups/tmp/`) and the assets bucket name
 * (the bucket the collector enumerates and the mirror uploads into).
 * The per-table bundles (`BackupTableExport`/`BackupTableImport`)
 * arrive as a separate constructor argument — the table names and
 * their export key columns are baked into the reader/writer instances
 * at wiring time, so the app layer never sees raw config arrays.
 */
final readonly class BackupSources
{
    public function __construct(
        public string $stagingPath,
        public string $assetsBucket,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Features\Backup\App\Services;

use App\Features\Backup\App\Contracts\AssetPathCollectorInterface;
use App\Features\Backup\App\Contracts\BackupArchiveInterface;
use App\Features\Backup\App\Contracts\StorageBatchTransferable;
use App\Features\Backup\App\DTOs\BackupSources;
use App\Features\Backup\Domain\Entities\Backup;
use App\Features\Backup\Domain\ValueObjects\Manifest;
use App\Features\Shared\App\Contracts\CacheClearerInterface;
use App\Features\Shared\App\Contracts\MaintenanceModeInterface;
use App\Features\Shared\App\Contracts\SitemapInvalidatorInterface;
use App\Features\Shared\App\Contracts\LoggerInterface;

use function basename;
use function file_exists;
use function in_array;
use function sprintf;

/**
 * The restore pipeline (backup.md:393-408) — the 10-step workflow:
 * maintenance mode on → archive download from the `backups` bucket →
 * validate + extract → the data plane (`DatabaseRestoreService`:
 * child-first truncate incl. the analytics wipe, `feature_flags`
 * never touched per ADR-0018, per-table JSONL import) → assets mirror
 * (upload + delete-extras) → cache/sitemap refresh → post-restore log
 * + lift maintenance → staging cleanup. On failure the site STAYS in
 * maintenance mode (backup.md:397) — the admin lifts it via the
 * Settings page or `admin:reset-maintenance`.
 *
 * Every collaborator is an app-layer contract (including the logger) —
 * no infra or framework classes are named here (ADR-0018). The entity's
 * `completed → processing` transition and the completion re-stamp are
 * the caller's concern (the restore use case); this service performs
 * the restore itself.
 */
final class RestoreWorkflowService
{
    public function __construct(
        private readonly StorageBatchTransferable $backupsTransfer,
        private readonly StorageBatchTransferable $assetsTransfer,
        private readonly BackupArchiveInterface $archive,
        private readonly AssetPathCollectorInterface $assetCollector,
        private readonly DatabaseRestoreService $databaseRestore,
        private readonly MaintenanceModeInterface $maintenance,
        private readonly SitemapInvalidatorInterface $sitemap,
        private readonly CacheClearerInterface $cacheClearer,
        private readonly LoggerInterface $logger,
        private readonly BackupSources $sources,
    ) {
    }

    public function execute(Backup $backup, string $rawKey): void
    {
        $runDir = StagingDirectory::create($this->sources->stagingPath);

        try {
            $this->maintenance->enable();

            $zipPath = $this->downloadArchive($backup, $runDir);
            $this->archive->validate($zipPath, $rawKey);
            $extractDir = $runDir.'/extracted';
            $this->archive->extract($zipPath, $rawKey, $extractDir);

            $this->databaseRestore->restore($extractDir);

            $manifest = $this->archive->readManifest($zipPath, $rawKey);
            $this->mirrorAssets($extractDir, $manifest);

            $this->cacheClearer->flush();
            $this->sitemap->invalidate();

            $this->logger->info('Backup restored', [
                'backup_id' => $backup->toId(),
                'filename' => $backup->toFilename(),
            ]);

            $this->maintenance->disable();
        } finally {
            StagingDirectory::remove($runDir);
        }
    }

    /**
     * Step 2: download the archive object into the staging run
     * (backup.md:398). The object key is the record's RustFS path.
     */
    private function downloadArchive(Backup $backup, string $runDir): string
    {
        $remotePath = $backup->toPath() ?? $backup->toFilename();
        $localPath = sprintf('%s/%s', $runDir, basename($remotePath));

        $this->backupsTransfer->downloadBatch([$remotePath => $localPath]);

        return $localPath;
    }

    /**
     * Step 7: mirror the extracted assets onto the portfolio bucket
     * (backup.md:403-405,451-454): every archive asset is uploaded
     * (overwriting), then every live object absent from the manifest is
     * deleted — the exact point-in-time mirror.
     */
    private function mirrorAssets(string $extractDir, Manifest $manifest): void
    {
        $uploadMap = [];

        foreach ($manifest->toAssets() as $asset) {
            $localPath = sprintf('%s/assets/%s', $extractDir, $asset->toPath());

            if (file_exists($localPath)) {
                $uploadMap[$localPath] = $asset->toPath();
            }
        }

        $this->assetsTransfer->uploadBatch($uploadMap);

        $manifestPaths = $manifest->assetPaths();
        $extras = [];

        foreach ($this->assetCollector->listPaths($this->sources->assetsBucket) as $path) {
            if (! in_array($path, $manifestPaths, true)) {
                $extras[] = $path;
            }
        }

        $this->assetsTransfer->deleteObjects($extras);
    }
}

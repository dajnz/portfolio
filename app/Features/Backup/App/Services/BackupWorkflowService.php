<?php

declare(strict_types=1);

namespace App\Features\Backup\App\Services;

use App\Features\Backup\App\Contracts\AssetPathCollectorInterface;
use App\Features\Backup\App\Contracts\BackupArchiveInterface;
use App\Features\Backup\App\Contracts\StorageBatchTransferable;
use App\Features\Backup\App\Contracts\StorageEstimatorInterface;
use App\Features\Backup\App\DTOs\BackupSources;
use App\Features\Backup\App\DTOs\BackupTableExport;
use App\Features\Backup\App\UseCases\PruneBackupsUseCase;
use App\Features\Backup\Domain\Entities\Backup;
use App\Features\Backup\Domain\RepositoryInterfaces\BackupRepositoryInterface;
use App\Features\Backup\Domain\ValueObjects\BackupContentSummary;
use App\Features\Backup\Domain\ValueObjects\Manifest;
use App\Features\Backup\Domain\ValueObjects\ManifestAsset;
use App\Features\Shared\App\Contracts\LoggerInterface;
use DateTimeImmutable;
use RuntimeException;

use function basename;
use function count;
use function dirname;
use function file_exists;
use function file_put_contents;
use function filesize;
use function is_dir;
use function mkdir;
use function sprintf;

/**
 * The backup creation pipeline — the full 10-step workflow: pre-flight
 * (2× space rule) → per-table JSONL export → asset collection +
 * download → manifest build → encrypted archive → upload to the
 * `backups` bucket → entity completion → staging cleanup → retention
 * prune. Runs inside `CreateBackupJob`; the entity's
 * `pending → processing` and failure transitions are the job's concern
 * — this service only completes.
 *
 * The workflow consumes only app-layer contracts: the per-table
 * readers/exporters arrive as ready {@see BackupTableExport} bundles and
 * the config-derived values via the {@see BackupSources} DTO — no infra
 * classes are named or instantiated here.
 */
final class BackupWorkflowService
{
    /**
     * @param  list<BackupTableExport>  $tables
     */
    public function __construct(
        private readonly AssetPathCollectorInterface $assetCollector,
        private readonly StorageBatchTransferable $assetsTransfer,
        private readonly StorageBatchTransferable $backupsTransfer,
        private readonly BackupArchiveInterface $archive,
        private readonly BackupRepositoryInterface $backupRepo,
        private readonly PruneBackupsUseCase $pruner,
        private readonly StorageEstimatorInterface $estimator,
        private readonly BackupSources $sources,
        private readonly array $tables,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(Backup $backup, string $rawKey, ?int $keyId = null): void
    {
        $startedAt = hrtime(true);
        $this->logger->info('Backup started', ['archive_name' => $backup->toFilename()]);

        $runDir = StagingDirectory::create($this->sources->stagingPath);

        try {
            $this->preflight();
            $records = $this->exportTables($runDir);
            $localAssets = $this->stageAssets($runDir);
            $manifest = $this->buildManifest($runDir, $records, $localAssets);

            $archivePath = $this->archive->create($runDir, $rawKey, $keyId, $backup->toFilename());
            $remotePath = basename($archivePath);

            $this->backupsTransfer->uploadBatch([$archivePath => $remotePath]);

            $backup->complete($remotePath, new BackupContentSummary(
                sizeBytes: (int) filesize($archivePath),
                assetsCount: $manifest->toAssetsCount(),
                assetsSizeBytes: $manifest->toAssetsSizeBytes(),
                recordCounts: $manifest->toRecordCounts(),
            ));

            $this->backupRepo->save($backup);

            $this->logger->info('Backup finished', [
                'archive_name' => $remotePath,
                'size' => (int) filesize($archivePath),
                'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            ]);
        } finally {
            StagingDirectory::remove($runDir);
        }

        $this->pruner->prune();
    }

    /**
     * Step 1: the 2× pre-flight rule — abort before any work when the
     * staging disk cannot hold `2 × (data + assets)`.
     */
    private function preflight(): void
    {
        $dataBytes = 0;

        foreach ($this->tables as $table) {
            $dataBytes += $this->estimator->estimateDataSize([$table->name], $table->reader);
        }

        $assetsBytes = $this->estimator->getAssetSize($this->sources->assetsBucket);

        if ($this->estimator->getAvailableSpace() < 2 * ($dataBytes + $assetsBytes)) {
            throw new RuntimeException(
                'Not enough disk space for the backup: at least 2× the data and asset size is required.',
            );
        }
    }

    /**
     * Step 2: export every included table to `{run}/data/{table}.jsonl`.
     * Returns the per-table row counts for the manifest — the exporter
     * reports the rows it wrote, so no separate DB count is needed.
     *
     * @return array<string, int>
     */
    private function exportTables(string $runDir): array
    {
        $dataDir = $runDir.'/data';

        if (! mkdir($dataDir, 0775, recursive: true) && ! is_dir($dataDir)) {
            throw new RuntimeException("Unable to create the export directory '{$dataDir}'.");
        }

        $records = [];

        foreach ($this->tables as $table) {
            $path = sprintf('%s/%s.jsonl', $dataDir, $table->name);

            $records[$table->name] = $table->exporter->writeToFile($table->reader, $path);
        }

        return $records;
    }

    /**
     * Steps 3-4: enumerate the assets bucket and download every object
     * into `{run}/assets/{path}`. Returns the `object key => local path`
     * map the manifest's sizes come from.
     *
     * @return array<string, string>
     */
    private function stageAssets(string $runDir): array
    {
        $assetsDir = $runDir.'/assets';
        $downloadMap = [];

        foreach ($this->assetCollector->listPaths($this->sources->assetsBucket) as $path) {
            $localPath = sprintf('%s/%s', $assetsDir, $path);

            if (! is_dir(dirname($localPath))) {
                mkdir(dirname($localPath), 0775, recursive: true);
            }

            $downloadMap[$path] = $localPath;
        }

        $this->assetsTransfer->downloadBatch($downloadMap);

        return $downloadMap;
    }

    /**
     * Step 5: write `manifest.json` — creation time, format version,
     * the exported record counts and the asset listing with the
     * downloaded byte sizes.
     *
     * @param  array<string, int>  $records
     * @param  array<string, string>  $localAssets  object key => local path
     */
    private function buildManifest(string $runDir, array $records, array $localAssets): Manifest
    {
        $assets = [];

        foreach ($localAssets as $path => $localPath) {
            $assets[] = new ManifestAsset(
                path: $path,
                sizeBytes: (int) (file_exists($localPath) ? filesize($localPath) : 0),
            );
        }

        $assetBytes = 0;

        foreach ($assets as $asset) {
            $assetBytes += $asset->toSizeBytes();
        }

        $manifest = new Manifest(
            createdAt: new DateTimeImmutable(),
            version: Manifest::VERSION,
            records: $records,
            assetsCount: count($assets),
            assetsSizeBytes: $assetBytes,
            assets: $assets,
        );

        file_put_contents($runDir.'/manifest.json', $manifest->toJson());

        return $manifest;
    }
}

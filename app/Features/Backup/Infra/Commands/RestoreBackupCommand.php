<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Commands;

use App\Features\Backup\App\UseCases\ImportFromPathUseCase;
use App\Features\Backup\App\UseCases\RestoreBackupUseCase;
use Illuminate\Console\Command;
use Throwable;

use function is_file;

/**
 * `backup:restore`: the synchronous restore. Normal mode restores a
 * backup from RustFS by filename (looks up the `backups` record,
 * resolves the key from `encryption_key_id` — or the legacy
 * `BACKUP_ENCRYPTION_KEY` fallback). DR mode with `--path` + `--key`
 * delegates to `ImportFromPathUseCase` (validate, upload, create the
 * key + records) and then restores the imported backup. Either mode
 * puts the site in maintenance mode during the restore; on failure the
 * site stays in maintenance and the command exits with the error.
 */
final class RestoreBackupCommand extends Command
{
    protected $signature = 'backup:restore {filename? : The backup filename to restore}
        {--path= : DR mode — local path of a downloaded backup .zip}
        {--key= : DR mode — the raw encryption key of the archive}';

    protected $description = 'Restore a backup from the backups bucket (or import one from a local .zip path)';

    public function handle(
        ImportFromPathUseCase $importCase,
        RestoreBackupUseCase $restoreCase,
    ): int {
        try {
            $filename = $this->restore($importCase, $restoreCase);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Backup %s restored successfully.', $filename));

        return self::SUCCESS;
    }

    private function restore(
        ImportFromPathUseCase $importCase,
        RestoreBackupUseCase $restoreCase,
    ): string {
        $filename = (string) $this->argument('filename');
        $path = (string) $this->option('path');
        $key = (string) $this->option('key');

        if ($path !== '' || $key !== '') {
            if ($path === '' || $key === '') {
                throw new \InvalidArgumentException('DR mode requires both --path and --key.');
            }

            if (! is_file($path)) {
                throw new \InvalidArgumentException("The backup file '{$path}' does not exist.");
            }

            $backup = $importCase->import($path, $key);
            $restoreCase->restore($backup);

            return $backup->toFilename();
        }

        if ($filename === '') {
            throw new \InvalidArgumentException('Provide the backup filename or use --path/--key for DR mode.');
        }

        $restoreCase->restoreByFilename($filename);

        return $filename;
    }
}

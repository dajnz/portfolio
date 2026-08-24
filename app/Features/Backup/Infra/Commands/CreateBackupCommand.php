<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Commands;

use App\Features\Backup\App\UseCases\CreateBackupUseCase;
use Illuminate\Console\Command;

/**
 * `backup:create` (backup.md:460): the thin scheduler entry point —
 * creates the `pending` `backups` row and dispatches `CreateBackupJob`
 * (the queued worker does the work; the command does not block the
 * scheduler). Scheduled daily at 02:00 (`routes/console.php`).
 */
final class CreateBackupCommand extends Command
{
    protected $signature = 'backup:create';

    protected $description = 'Create a pending backup record and dispatch the backup job';

    public function handle(CreateBackupUseCase $create): int
    {
        $backup = $create->create();

        $this->info(sprintf('Backup job dispatched (record #%d, %s).', $backup->toId(), $backup->toFilename()));

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Commands;

use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * `app:prune-trashed-items` (content-management.md:904-936) — hard-deletes
 * entities soft-deleted for longer than the `trash_retention_days` setting
 * (default 30; 0 disables pruning). Applies to blog articles, projects and
 * experience timeline items; deletion is chunked (50 per chunk) to avoid
 * loading everything into memory. Registered on a monthly schedule in
 * routes/console.php.
 *
 * Note: per-model `forceDelete()` file cleanup (screenshot/image deletion
 * from RustFS — content-management.md:813-819) is wired when the feature
 * Eloquent models land (steps 6.1/7.1/8.1); this command currently removes
 * the orphaned DB rows only.
 */
final class PruneTrashedItemsCommand extends Command
{
    protected $signature = 'app:prune-trashed-items';

    protected $description = 'Hard-delete entities soft-deleted beyond the trash retention period';

    public function __construct(
        private readonly SettingRepositoryInterface $settings,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $retentionDays = (int) $this->settings->value(SettingName::TrashRetentionDays, 30);

        if ($retentionDays <= 0) {
            $this->info('Trash pruning is disabled (trash_retention_days = 0).');

            return self::SUCCESS;
        }

        $cutoff = (new DateTimeImmutable())->modify("-{$retentionDays} days");
        $deleted = 0;

        foreach (['blog_articles', 'projects', 'experience_timeline_items'] as $table) {
            $deleted += $this->pruneTable($table, $cutoff);
        }

        $this->info("Pruned {$deleted} trashed item(s) older than {$retentionDays} day(s).");

        return self::SUCCESS;
    }

    private function pruneTable(string $table, DateTimeImmutable $cutoff): int
    {
        $deleted = 0;

        DB::table($table)
            ->whereNotNull('deleted_at')
            ->where('deleted_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(50, function ($rows) use ($table, &$deleted): void {
                $deleted += DB::table($table)->whereIn('id', $rows->pluck('id'))->delete();
            });

        return $deleted;
    }
}

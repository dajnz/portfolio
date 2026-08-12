<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Commands;

use App\Features\Projects\Infra\Models\ProjectModel;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use App\Features\Shared\App\Contracts\AssetCleanerInterface;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Hard-deletes entities soft-deleted beyond the trash retention period
 * (default 30; 0 disables pruning). Applies to blog articles, projects
 * and experience timeline items; deletion is chunked (50 per chunk) to
 * avoid loading everything into memory. Registered on a monthly
 * schedule in routes/console.php.
 *
 * Projects keep their slug history (`old_slugs` records — content-
 * management.md:905) and release their RustFS files (preview image +
 * screenshots) via the shared asset cleaner — the file orchestration
 * lives here, not in the Eloquent models; the blog/experience tables
 * stay query-builder deletes until their feature steps (timeline items
 * carry no files).
 */
final class PruneTrashedItemsCommand extends Command
{
    protected $signature = 'app:prune-trashed-items';

    protected $description = 'Hard-delete entities soft-deleted beyond the trash retention period';

    public function __construct(
        private readonly SettingRepositoryInterface $settings,
        private readonly AssetCleanerInterface $assets,
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

        foreach (['blog_articles', 'experience_timeline_items'] as $table) {
            $deleted += $this->pruneTable($table, $cutoff);
        }

        $deleted += $this->pruneProjects($cutoff);

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
                $ids = $rows->pluck('id')->all();
                DB::table($table)->whereIn('id', $ids)->delete();
                $deleted += count($ids);
            });

        return $deleted;
    }

    /**
     * Projects keep their slug history (`old_slugs` upsert) and their
     * RustFS files are released via the asset cleaner — the DB rows are
     * then hard-deleted in one batched query per chunk.
     */
    private function pruneProjects(DateTimeImmutable $cutoff): int
    {
        $deleted = 0;

        ProjectModel::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(50, function ($projects) use (&$deleted): void {
                $ids = $projects->pluck('id')->all();

                $this->recordSlugHistory($projects);
                $this->assets->delete($this->assetPathsOf($ids));

                ProjectModel::withTrashed()->whereIn('id', $ids)->forceDelete();
                $deleted += count($ids);
            });

        return $deleted;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ProjectModel>  $projects
     */
    private function recordSlugHistory($projects): void
    {
        $rows = $projects->map(static fn (ProjectModel $project): array => [
            'old_slug' => $project->slug,
            'entity_type' => 'project',
            'entity_id' => $project->id,
        ])->all();

        DB::table('old_slugs')->upsert(
            $rows,
            ['old_slug', 'entity_type'],
            ['entity_id'],
        );
    }

    /**
     * The chunk's RustFS asset paths — the preview images and every
     * screenshot (content-management.md:827-828).
     *
     * @param  int[]  $ids
     * @return string[]
     */
    private function assetPathsOf(array $ids): array
    {
        $paths = ProjectModel::withTrashed()
            ->whereIn('id', $ids)
            ->pluck('preview_image')
            ->filter()
            ->all();

        $screenshotPaths = DB::table('project_screenshots')
            ->whereIn('project_id', $ids)
            ->pluck('image_path')
            ->all();

        return array_merge($paths, $screenshotPaths);
    }
}

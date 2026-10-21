<?php

declare(strict_types=1);

namespace App\Features\Projects\Infra\Services;

use App\Features\Projects\Infra\Models\ProjectModel;
use App\Features\Shared\App\Contracts\AssetCleanerInterface;
use App\Features\Shared\App\Contracts\TrashedItemPrunerInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Hard-deletes projects past the trash retention window. Projects keep
 * their slug history (`old_slugs` upsert) and release their RustFS files
 * through the shared asset cleaner before the rows are force-deleted.
 */
final class EloquentTrashedProjectPruner implements TrashedItemPrunerInterface
{
    private const CHUNK_SIZE = 50;

    public function __construct(
        private readonly AssetCleanerInterface $assets,
    ) {
    }

    public function prune(DateTimeImmutable $cutoff): int
    {
        $deleted = 0;

        ProjectModel::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function ($projects) use (&$deleted): void {
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

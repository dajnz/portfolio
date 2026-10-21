<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Shared\App\Contracts\AssetPathProviderInterface;
use Illuminate\Support\Facades\DB;

/**
 * The database-backed asset references: direct image/file columns plus
 * image paths embedded in article Markdown and HTML.
 */
final class DatabaseAssetPathProvider implements AssetPathProviderInterface
{
    public function paths(): array
    {
        $paths = [];

        foreach ($this->columnPaths('projects', 'preview_image') as $path) {
            $paths[] = $path;
        }

        foreach ($this->columnPaths('project_screenshots', 'image_path') as $path) {
            $paths[] = $path;
        }

        foreach ($this->columnPaths('blog_articles', 'lead_image') as $path) {
            $paths[] = $path;
        }

        foreach ($this->columnPaths('about_info', 'photo') as $path) {
            $paths[] = $path;
        }

        foreach ($this->columnPaths('about_info', 'resume_file') as $path) {
            $paths[] = $path;
        }

        foreach ($this->columnPaths('resume_config', 'custom_resume_file') as $path) {
            $paths[] = $path;
        }

        foreach ($this->markdownImagePaths() as $path) {
            $paths[] = $path;
        }

        return array_values(array_filter(array_unique($paths)));
    }

    /**
     * @return string[]
     */
    private function columnPaths(string $table, string $column): array
    {
        return DB::table($table)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->pluck($column)
            ->filter(fn ($value): bool => is_string($value))
            ->all();
    }

    /**
     * @return string[]
     */
    private function markdownImagePaths(): array
    {
        $contents = DB::table('blog_article_translations')->pluck('content');
        $paths = [];

        foreach ($contents as $content) {
            if (! is_string($content) || $content === '') {
                continue;
            }

            preg_match_all('/!\[[^\]]*\]\(([^)\s]+)\)/', $content, $markdown);
            preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/', $content, $html);

            foreach ([...$markdown[1], ...$html[1]] as $path) {
                $storagePath = $this->toStoragePath($path);

                if ($storagePath !== null) {
                    $paths[] = $storagePath;
                }
            }
        }

        return $paths;
    }

    /**
     * Normalizes an image reference to its RustFS object key. The admin
     * editor stores inline images as `/assets/{key}` while direct columns
     * store the bare `{key}` — both must resolve to the same key.
     */
    private function toStoragePath(mixed $path): ?string
    {
        if (! is_string($path) || $path === '' || str_starts_with($path, 'http')) {
            return null;
        }

        if (str_starts_with($path, '/assets/')) {
            return substr($path, strlen('/assets/'));
        }

        return ltrim($path, '/');
    }
}

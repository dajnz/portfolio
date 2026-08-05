<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * `app:cleanup-orphaned-files` (backup.md:465, content-management.md:821-854)
 * — deletes RustFS objects in the assets bucket (`rustfs` disk) that are
 * not referenced by any DB column or embedded Markdown image. Supports
 * `--dry-run` to list orphans without deleting. Registered on a monthly
 * schedule in routes/console.php; the temp backup dir
 * (`storage/app/backups/tmp/`) is never scanned.
 *
 * Reference sources: `projects.preview_image`, `project_screenshots.image_path`,
 * `blog_articles.lead_image`, `about_info.photo`, `about_info.resume_file`,
 * `resume_config.custom_resume_file`, and image paths extracted from
 * `blog_article_translations.content` (Markdown `![alt](path)` + HTML
 * `<img src="path">`). The expected generated resume PDFs
 * (`about/resume_{locale}_{content_hash}.pdf`) are matched once the content
 * hash computation lands with the Resume feature (step 10.1).
 */
final class CleanupOrphanedFilesCommand extends Command
{
    protected $signature = 'app:cleanup-orphaned-files
        {--dry-run : List orphaned files without deleting them}';

    protected $description = 'Delete RustFS objects not referenced by any DB column or Markdown content';

    public function handle(): int
    {
        $disk = Storage::disk('rustfs');

        $referenced = $this->referencedPaths();
        $orphans = [];

        foreach ($disk->allFiles('/') as $path) {
            if (! in_array($path, $referenced, true)) {
                $orphans[] = $path;
            }
        }

        if ($orphans === []) {
            $this->info('No orphaned files found.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $deleted = 0;

        foreach ($orphans as $path) {
            if ($dryRun) {
                $this->line("Would delete: {$path}");
            } else {
                $disk->delete($path);
                $this->line("Deleted: {$path}");
            }
            $deleted++;
        }

        $this->info(($dryRun ? 'Orphans found: ' : 'Deleted: ').$deleted);

        return self::SUCCESS;
    }

    /**
     * @return string[] every path the assets bucket is expected to contain
     */
    private function referencedPaths(): array
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
     * @return string[] non-empty values of the given column
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
     * @return string[] image paths extracted from article Markdown content
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
                if (is_string($path) && $path !== '' && ! str_starts_with($path, 'http')) {
                    $paths[] = $path;
                }
            }
        }

        return $paths;
    }
}

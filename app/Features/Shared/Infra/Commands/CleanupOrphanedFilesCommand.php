<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Commands;

use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * `app:cleanup-orphaned-files` — deletes RustFS objects in the assets bucket
 * (`rustfs` disk) that are not referenced by any DB column or embedded Markdown
 * image. Supports `--dry-run` to list orphans without deleting. Registered on a
 * monthly schedule in routes/console.php; the temp backup dir
 * (`storage/app/backups/tmp/`) is never scanned.
 *
 * Reference sources: `projects.preview_image`, `project_screenshots.image_path`,
 * `blog_articles.lead_image`, `about_info.photo`, `about_info.resume_file`,
 * `resume_config.custom_resume_file`, and image paths extracted from
 * `blog_article_translations.content` (Markdown `![alt](path)` + HTML
 * `<img src="path">`). The generated resume PDFs
 * (`about/resume_{locale}_{content_hash}.pdf`) matching the CURRENT content hash
 * are preserved — the hash is recomputed here with the same source stamps the
 * Resume feature uses, so stale-hash PDFs fall out as orphans.
 */
final class CleanupOrphanedFilesCommand extends Command
{
    protected $signature = 'app:cleanup-orphaned-files
        {--dry-run : List orphaned files without deleting them}';

    protected $description = 'Delete RustFS objects not referenced by any DB column or Markdown content';

    public function __construct(
        private readonly SettingRepositoryInterface $settings,
    ) {
        parent::__construct();
    }

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

        foreach ($this->generatedResumePdfPaths() as $path) {
            $paths[] = $path;
        }

        return array_values(array_filter(array_unique($paths)));
    }

    /**
     * The expected generated resume PDFs: `about/resume_{locale}_{hash}.pdf`
     * for every active locale with the CURRENT content hash. The hash mirrors
     * the Resume feature's computation (source `updated_at` stamps + config stamp
     * + layout JSON) so PDFs of older hashes are not referenced and get cleaned
     * up.
     *
     * @return string[]
     */
    private function generatedResumePdfPaths(): array
    {
        $config = DB::table('resume_config')->where('id', 1)->first();

        if ($config === null) {
            return [];
        }

        $layout = is_string($config->layout) ? json_decode($config->layout, true) : null;

        $hash = hash('sha256', json_encode([
            'stamps' => [
                'about_info' => DB::table('about_info')->value('updated_at'),
                'skill_groups' => $this->stampsOf('skill_groups'),
                'skills' => $this->stampsOf('skills'),
                'contacts' => $this->stampsOf('contacts'),
                'experience_timeline_items' => $this->stampsOf('experience_timeline_items'),
            ],
            'config_updated_at' => $config->updated_at,
            'layout' => $layout,
        ], JSON_THROW_ON_ERROR));

        $paths = [];

        foreach ($this->activeLocales() as $locale) {
            $paths[] = "about/resume_{$locale}_{$hash}.pdf";
        }

        return $paths;
    }

    /**
     * @return string[]
     */
    private function stampsOf(string $table): array
    {
        return DB::table($table)
            ->orderBy('id')
            ->pluck('updated_at')
            ->map(fn (mixed $stamp): string => (string) $stamp)
            ->all();
    }

    /**
     * @return string[]
     */
    private function activeLocales(): array
    {
        $locales = $this->settings->value(
            SettingName::SupportedLocales,
            Config::get('portfolio.supported_locales', ['en', 'ru']),
        );

        return array_values(array_unique(array_map('strval', (array) $locales)));
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
     * store the bare `{key}` — both must resolve to the same key so the
     * cleanup does not delete referenced objects.
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

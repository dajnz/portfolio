<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Shared;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

describe('CleanupOrphanedFilesCommand', function (): void {
    beforeEach(function (): void {
        Storage::fake('rustfs');
    });

    it('deletes RustFS objects not referenced by any DB column', function (): void {
        Storage::disk('rustfs')->put('blog/kept/lead.jpg', 'a');
        Storage::disk('rustfs')->put('projects/kept/shot.png', 'b');
        Storage::disk('rustfs')->put('about/photo.jpg', 'c');
        Storage::disk('rustfs')->put('blog/orphaned.jpg', 'd');

        DB::table('blog_articles')->insert([
            'slug' => 'kept', 'is_published' => true, 'lead_image' => 'blog/kept/lead.jpg',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('projects')->insert([
            'slug' => 'kept-project', 'is_published' => true, 'preview_image' => 'projects/kept/shot.png',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('about_info')->insert([
            'id' => 1, 'photo' => 'about/photo.jpg', 'resume_file' => null,
        ]);

        $this->artisan('app:cleanup-orphaned-files')->assertSuccessful();

        expect(Storage::disk('rustfs')->exists('blog/kept/lead.jpg'))->toBeTrue()
            ->and(Storage::disk('rustfs')->exists('projects/kept/shot.png'))->toBeTrue()
            ->and(Storage::disk('rustfs')->exists('about/photo.jpg'))->toBeTrue()
            ->and(Storage::disk('rustfs')->exists('blog/orphaned.jpg'))->toBeFalse();
    });

    it('treats Markdown image paths inside article content as references', function (): void {
        Storage::disk('rustfs')->put('blog/markdown/image.png', 'x');

        $articleId = DB::table('blog_articles')->insertGetId([
            'slug' => 'md', 'is_published' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('blog_article_translations')->insert([
            'article_id' => $articleId, 'locale' => 'en',
            'title' => 'MD', 'short_description' => 's', 'content' => '![alt](blog/markdown/image.png)',
            'reading_time_minutes' => 1,
        ]);

        $this->artisan('app:cleanup-orphaned-files')->assertSuccessful();

        expect(Storage::disk('rustfs')->exists('blog/markdown/image.png'))->toBeTrue();
    });

    it('resolves /assets-prefixed Markdown image references to their storage keys', function (): void {
        Storage::disk('rustfs')->put('blog/markdown/editor-image.png', 'x');
        Storage::disk('rustfs')->put('blog/markdown/orphaned.png', 'y');

        $articleId = DB::table('blog_articles')->insertGetId([
            'slug' => 'md-assets', 'is_published' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('blog_article_translations')->insert([
            'article_id' => $articleId, 'locale' => 'en',
            'title' => 'MD', 'short_description' => 's',
            'content' => '![alt](/assets/blog/markdown/editor-image.png)',
            'reading_time_minutes' => 1,
        ]);

        $this->artisan('app:cleanup-orphaned-files')->assertSuccessful();

        expect(Storage::disk('rustfs')->exists('blog/markdown/editor-image.png'))->toBeTrue()
            ->and(Storage::disk('rustfs')->exists('blog/markdown/orphaned.png'))->toBeFalse();
    });

    it('lists orphans without deleting them with --dry-run', function (): void {
        Storage::disk('rustfs')->put('blog/orphaned.jpg', 'd');

        $this->artisan('app:cleanup-orphaned-files', ['--dry-run' => true])
            ->expectsOutputToContain('Would delete: blog/orphaned.jpg')
            ->assertSuccessful();

        expect(Storage::disk('rustfs')->exists('blog/orphaned.jpg'))->toBeTrue();
    });

    it('reports when no orphaned files exist', function (): void {
        $this->artisan('app:cleanup-orphaned-files')
            ->expectsOutputToContain('No orphaned files found.')
            ->assertSuccessful();
    });

    it('preserves the generated resume PDFs matching the current content hash', function (): void {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        DB::table('resume_config')->insert([
            'id' => 1,
            'mode' => 'auto',
            'layout' => json_encode([
                'template' => 'modern',
                'sections' => [
                    'header' => ['visible' => true, 'order' => 0],
                    'about_me' => ['visible' => true, 'order' => 1],
                    'skills' => ['visible' => true, 'order' => 2],
                    'experience' => ['visible' => true, 'order' => 3],
                    'contacts' => ['visible' => true, 'order' => 4],
                ],
                'skills_layout' => 'per_line',
                'contacts_show_heading' => true,
                'contacts_layout' => 'per_line',
                'experience_show_skills' => true,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('about_info')->insert([
            'id' => 1,
            'show_location' => true,
            'relocation_readiness' => false,
            'work_type' => 'remote',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('skill_groups')->insert([
            'id' => 1,
            'sort_order' => 0,
            'icon' => 'heroicons:server',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('skills')->insert([
            'id' => 1,
            'skill_group_id' => 1,
            'name' => 'Laravel',
            'icon' => 'devicon:laravel',
            'proficiency' => 5,
            'include_in_resume' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $layout = DB::table('resume_config')->value('layout');
        $stamps = fn (string $table): array => DB::table($table)
            ->orderBy('id')
            ->pluck('updated_at')
            ->map(fn (mixed $stamp): string => (string) $stamp)
            ->all();
        $hash = hash('sha256', json_encode([
            'stamps' => [
                'about_info' => DB::table('about_info')->value('updated_at'),
                'skill_groups' => $stamps('skill_groups'),
                'skills' => $stamps('skills'),
                'contacts' => $stamps('contacts'),
                'experience_timeline_items' => $stamps('experience_timeline_items'),
            ],
            'config_updated_at' => DB::table('resume_config')->value('updated_at'),
            'layout' => json_decode((string) $layout, true),
        ], JSON_THROW_ON_ERROR));

        Storage::disk('rustfs')->put("about/resume_en_{$hash}.pdf", 'a');
        Storage::disk('rustfs')->put("about/resume_ru_{$hash}.pdf", 'b');
        Storage::disk('rustfs')->put('about/resume_en_oldhash.pdf', 'c');

        $this->artisan('app:cleanup-orphaned-files')->assertSuccessful();

        expect(Storage::disk('rustfs')->exists("about/resume_en_{$hash}.pdf"))->toBeTrue()
            ->and(Storage::disk('rustfs')->exists("about/resume_ru_{$hash}.pdf"))->toBeTrue()
            ->and(Storage::disk('rustfs')->exists('about/resume_en_oldhash.pdf'))->toBeFalse();
    });
});

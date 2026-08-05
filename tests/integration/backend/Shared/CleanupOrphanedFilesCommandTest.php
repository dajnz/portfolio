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
});

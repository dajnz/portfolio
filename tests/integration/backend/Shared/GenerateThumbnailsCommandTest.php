<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Shared;

use App\Features\Blog\Infra\Models\ArticleModel;
use App\Features\Projects\Infra\Models\ProjectScreenshotModel;
use App\Features\Shared\Domain\ValueObjects\ThumbnailPath;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImageFixtures;

beforeEach(function (): void {
    $this->seed(SettingsSeeder::class);
    Storage::fake('rustfs');
});

describe('app:generate-thumbnails', function (): void {
    it('generates the missing thumbnails inline', function (): void {
        $screenshotPath = 'projects/backfill.webp';
        $leadImagePath = 'blog/backfill.webp';
        Storage::disk('rustfs')->put($screenshotPath, ImageFixtures::png(1200, 800));
        Storage::disk('rustfs')->put($leadImagePath, ImageFixtures::png(1200, 800));

        $screenshot = ProjectScreenshotModel::factory()->create(['image_path' => $screenshotPath]);
        $article = ArticleModel::factory()->create(['lead_image' => $leadImagePath]);

        $this->artisan('app:generate-thumbnails', ['--sync' => true])
            ->expectsOutputToContain('Generated 1 screenshot thumbnail(s) and 1 article thumbnail(s).')
            ->assertSuccessful();

        expect($screenshot->fresh()->thumbnail_path)->toBe(ThumbnailPath::forSource($screenshotPath)->toStandard())
            ->and($article->fresh()->thumbnail_path)->toBe(ThumbnailPath::forSource($leadImagePath)->toStandard())
            ->and(Storage::disk('rustfs')->exists(ThumbnailPath::forSource($screenshotPath)->toRetina()))->toBeTrue()
            ->and(Storage::disk('rustfs')->exists(ThumbnailPath::forSource($leadImagePath)->toRetina()))->toBeTrue();
    });

    it('only regenerates the rows without a thumbnail unless forced', function (): void {
        $path = 'projects/backfill-forced.webp';
        Storage::disk('rustfs')->put($path, ImageFixtures::png(1200, 800));

        $screenshot = ProjectScreenshotModel::factory()->create(['image_path' => $path]);

        $this->artisan('app:generate-thumbnails', ['--sync' => true])->assertSuccessful();
        Storage::disk('rustfs')->delete(ThumbnailPath::forSource($path)->toStandard());

        $this->artisan('app:generate-thumbnails', ['--sync' => true])
            ->expectsOutputToContain('Generated 0 screenshot thumbnail(s) and 0 article thumbnail(s).')
            ->assertSuccessful();

        expect(Storage::disk('rustfs')->exists(ThumbnailPath::forSource($path)->toStandard()))->toBeFalse();

        $this->artisan('app:generate-thumbnails', ['--sync' => true, '--force' => true])
            ->assertSuccessful();

        expect(Storage::disk('rustfs')->exists(ThumbnailPath::forSource($path)->toStandard()))->toBeTrue()
            ->and($screenshot->fresh()->thumbnail_path)->toBe(ThumbnailPath::forSource($path)->toStandard());
    });

    it('skips rows whose source object is missing', function (): void {
        $screenshot = ProjectScreenshotModel::factory()->create(['image_path' => 'projects/gone.webp']);

        $this->artisan('app:generate-thumbnails', ['--sync' => true])->assertSuccessful();

        expect($screenshot->fresh()->thumbnail_path)->toBeNull()
            ->and(Storage::disk('rustfs')->allFiles())->toBe([]);
    });
});

<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Shared;

use App\Features\Projects\Infra\Models\ProjectScreenshotModel;
use App\Features\Shared\App\UseCases\CleanupOrphanedFilesUseCase;
use App\Features\Shared\Domain\ValueObjects\ThumbnailPath;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(SettingsSeeder::class);
    Storage::fake('rustfs');
});

describe('thumbnail orphan cleanup', function (): void {
    it('keeps the derived thumbnails of a referenced screenshot', function (): void {
        $source = 'projects/shot.webp';
        ProjectScreenshotModel::factory()->create(['image_path' => $source]);

        Storage::disk('rustfs')->put($source, 'source-bytes');
        Storage::disk('rustfs')->put(ThumbnailPath::forSource($source)->toStandard(), 'thumb-bytes');
        Storage::disk('rustfs')->put(ThumbnailPath::forSource($source)->toRetina(), 'retina-bytes');
        Storage::disk('rustfs')->put('projects/unreferenced.webp', 'orphan-bytes');

        app(CleanupOrphanedFilesUseCase::class)->cleanup(false);

        expect(Storage::disk('rustfs')->exists(ThumbnailPath::forSource($source)->toStandard()))->toBeTrue()
            ->and(Storage::disk('rustfs')->exists(ThumbnailPath::forSource($source)->toRetina()))->toBeTrue()
            ->and(Storage::disk('rustfs')->exists($source))->toBeTrue()
            ->and(Storage::disk('rustfs')->exists('projects/unreferenced.webp'))->toBeFalse();
    });

    it('removes the thumbnails when the source row and object are gone', function (): void {
        Storage::disk('rustfs')->put(ThumbnailPath::forSource('projects/gone.webp')->toStandard(), 'thumb-bytes');
        Storage::disk('rustfs')->put(ThumbnailPath::forSource('projects/gone.webp')->toRetina(), 'retina-bytes');

        app(CleanupOrphanedFilesUseCase::class)->cleanup(false);

        expect(Storage::disk('rustfs')->allFiles())->toBe([]);
    });
});

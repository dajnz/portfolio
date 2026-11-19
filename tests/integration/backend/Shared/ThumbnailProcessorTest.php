<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Shared;

use App\Features\Shared\Infra\Services\UploadedImageProcessor;
use Illuminate\Support\Facades\Config;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Tests\Support\ImageFixtures;

function makeThumbnailProcessor(): UploadedImageProcessor
{
    return new UploadedImageProcessor(new ImageManager(new Driver()));
}

function thumbnailSize(string $bytes): array
{
    $decoded = imagecreatefromstring($bytes);

    return [imagesx($decoded), imagesy($decoded)];
}

describe('UploadedImageProcessor thumbnails', function (): void {
    beforeEach(function (): void {
        Config::set('portfolio.thumbnail', ['width' => 480, 'height' => 270, 'quality' => 80]);
    });

    it('encodes the 1x box as a WebP container', function (): void {
        $bytes = makeThumbnailProcessor()->toThumbnail(
            ImageFixtures::png(1200, 800),
            480,
            270,
            80,
        );

        expect(str_starts_with($bytes, 'RIFF'))->toBeTrue()
            ->and(substr($bytes, 8, 4))->toBe('WEBP')
            ->and(thumbnailSize($bytes))->toBe([480, 270]);
    });

    it('center-crops a non-16:9 source to the box aspect ratio', function (): void {
        $bytes = makeThumbnailProcessor()->toThumbnail(
            ImageFixtures::png(1200, 800),
            960,
            540,
            80,
        );

        [$width, $height] = thumbnailSize($bytes);

        expect($width)->toBe(960)
            ->and($height)->toBe(540);
    });

    it('never upscales a source smaller than the box', function (): void {
        $bytes = makeThumbnailProcessor()->toThumbnail(
            ImageFixtures::png(300, 200),
            480,
            270,
            80,
        );

        [$width, $height] = thumbnailSize($bytes);

        expect($width)->toBe(300)
            ->and($height)->toBeGreaterThanOrEqual(168)
            ->and($height)->toBeLessThanOrEqual(170);
    });
});

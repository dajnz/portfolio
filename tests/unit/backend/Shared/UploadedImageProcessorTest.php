<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Shared;

use App\Features\Shared\Infra\Exceptions\InvalidUploadedImageException;
use App\Features\Shared\Infra\Services\UploadedImageProcessor;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Tests\Support\ImageFixtures;

function makeUploadedImageProcessor(): UploadedImageProcessor
{
    return new UploadedImageProcessor(new ImageManager(new Driver()));
}

function writeImageTempFile(string $bytes): string
{
    $path = tempnam(sys_get_temp_dir(), 'img-');

    file_put_contents($path, $bytes);

    return $path;
}

describe('UploadedImageProcessor', function (): void {
    it('encodes a small PNG as WebP without upscaling', function (): void {
        $bytes = makeUploadedImageProcessor()->toWebp(
            writeImageTempFile(ImageFixtures::png(120, 80)),
            2048,
        );

        expect(str_starts_with($bytes, 'RIFF'))->toBeTrue();

        $decoded = imagecreatefromstring($bytes);

        expect($decoded)->not->toBeFalse()
            ->and(imagesx($decoded))->toBe(120)
            ->and(imagesy($decoded))->toBe(80);
    });

    it('scales the largest side down to the limit keeping the aspect ratio', function (): void {
        $bytes = makeUploadedImageProcessor()->toWebp(
            writeImageTempFile(ImageFixtures::png(3000, 2000)),
            2048,
        );

        $decoded = imagecreatefromstring($bytes);

        expect(imagesx($decoded))->toBe(2048)
            ->and(imagesy($decoded))->toBe(1365);
    });

    it('scales portrait images down on their largest side', function (): void {
        $bytes = makeUploadedImageProcessor()->toWebp(
            writeImageTempFile(ImageFixtures::png(2000, 3000)),
            2048,
        );

        $decoded = imagecreatefromstring($bytes);

        expect(imagesx($decoded))->toBe(1365)
            ->and(imagesy($decoded))->toBe(2048);
    });

    it('honors an explicit dimension limit', function (): void {
        $bytes = makeUploadedImageProcessor()->toWebp(
            writeImageTempFile(ImageFixtures::png(3000, 2000)),
            1000,
        );

        $decoded = imagecreatefromstring($bytes);

        expect(imagesx($decoded))->toBe(1000)
            ->and(imagesy($decoded))->toBe(667);
    });

    it('applies the EXIF auto-orientation to JPEG uploads', function (): void {
        $bytes = makeUploadedImageProcessor()->toWebp(
            writeImageTempFile(ImageFixtures::jpegWithOrientation(100, 200, 6)),
            2048,
        );

        $decoded = imagecreatefromstring($bytes);

        expect(imagesx($decoded))->toBe(200)
            ->and(imagesy($decoded))->toBe(100);
    });

    it('keeps the alpha channel when converting a transparent PNG', function (): void {
        $bytes = makeUploadedImageProcessor()->toWebp(
            writeImageTempFile(ImageFixtures::png(64, 64, alpha: true)),
            2048,
        );

        $decoded = imagecreatefromstring($bytes);
        $pixel = imagecolorat($decoded, 0, 0);

        expect(($pixel >> 24) & 0x7F)->toBeGreaterThan(0);
    });

    it('strips the EXIF metadata during the WebP conversion', function (): void {
        $bytes = makeUploadedImageProcessor()->toWebp(
            writeImageTempFile(ImageFixtures::jpegWithOrientation(100, 200, 6)),
            2048,
        );

        expect($bytes)->not->toContain('Exif')
            ->not->toContain('Orientation');
    });

    it('converts WebP, AVIF and JPEG input alike', function (): void {
        $sources = [ImageFixtures::webp(300, 200), ImageFixtures::jpeg(300, 200)];

        if (function_exists('imageavif')) {
            $sources[] = ImageFixtures::avif(300, 200);
        }

        foreach ($sources as $source) {
            $bytes = makeUploadedImageProcessor()->toWebp(
                writeImageTempFile($source),
                2048,
            );

            $decoded = imagecreatefromstring($bytes);

            expect($decoded)->not->toBeFalse()
                ->and(imagesx($decoded))->toBe(300);
        }
    });

    it('rejects undecodable content with InvalidUploadedImageException', function (): void {
        $path = writeImageTempFile(str_repeat('not-an-image', 100));

        expect(fn () => makeUploadedImageProcessor()->toWebp($path, 2048))
            ->toThrow(InvalidUploadedImageException::class);
    });
});

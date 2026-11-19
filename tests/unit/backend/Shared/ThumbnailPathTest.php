<?php

declare(strict_types=1);

use App\Features\Shared\Domain\ValueObjects\ThumbnailPath;

describe('ThumbnailPath', function (): void {
    it('derives both variants from the source path', function (): void {
        $paths = ThumbnailPath::forSource('projects/ab12.webp');

        expect($paths->toStandard())->toBe('projects/ab12.thumb.webp')
            ->and($paths->toRetina())->toBe('projects/ab12.thumb@2x.webp');
    });

    it('derives the variants of a non-webp source as well', function (): void {
        $paths = ThumbnailPath::forSource('blog/xy.gif');

        expect($paths->toStandard())->toBe('blog/xy.thumb.webp')
            ->and($paths->toRetina())->toBe('blog/xy.thumb@2x.webp');
    });

    it('keeps dots of directory names intact', function (): void {
        expect(ThumbnailPath::forSource('demo/v1.2/screen-1.webp')->toStandard())
            ->toBe('demo/v1.2/screen-1.thumb.webp');
    });

    it('keeps extension-less paths as-is before the suffix', function (): void {
        expect(ThumbnailPath::forSource('about/photo')->toStandard())
            ->toBe('about/photo.thumb.webp');
    });

    it('exposes the source and both variants as the delete file set', function (): void {
        expect(ThumbnailPath::forSource('projects/ab12.webp')->toFiles())->toBe([
            'projects/ab12.webp',
            'projects/ab12.thumb.webp',
            'projects/ab12.thumb@2x.webp',
        ]);
    });

    it('rejects an empty source path', function (): void {
        ThumbnailPath::forSource('');
    })->throws(InvalidArgumentException::class);
});

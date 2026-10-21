<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Backup;

use App\Features\Backup\Domain\ValueObjects\ManifestAsset;
use InvalidArgumentException;

describe('ManifestAsset', function (): void {
    it('validates the asset path and size', function (): void {
        expect(fn () => new ManifestAsset('', 10))->toThrow(
            InvalidArgumentException::class,
            'Manifest asset path must not be empty.',
        );

        expect(fn () => new ManifestAsset('projects/abc123/preview.jpg', -1))->toThrow(
            InvalidArgumentException::class,
            'Manifest asset size must not be negative.',
        );
    });
});

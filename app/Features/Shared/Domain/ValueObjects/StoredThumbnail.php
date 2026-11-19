<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * A generated card thumbnail reference: the 1x WebP object and its 2x
 * variant. Both are written by the same job, so a stored picture has
 * either the full pair or none.
 */
final readonly class StoredThumbnail
{
    public function __construct(
        public string $url,
        public string $retinaUrl,
    ) {
        if ($url === '' || $retinaUrl === '') {
            throw new InvalidArgumentException('Stored thumbnail URLs must not be empty.');
        }
    }

    /**
     * Builds the pair from the image's source path and the stored 1x
     * thumbnail path — the 2x object follows the {@see ThumbnailPath}
     * convention of the source. Null when the thumbnail was not generated
     * yet.
     */
    public static function fromSource(string $sourcePath, ?string $thumbnailPath): ?self
    {
        if ($thumbnailPath === null || $thumbnailPath === '') {
            return null;
        }

        return new self(
            $thumbnailPath,
            ThumbnailPath::forSource($sourcePath)->toRetina(),
        );
    }
}

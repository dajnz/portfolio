<?php

declare(strict_types=1);

namespace App\Features\Shared\App\DTOs;

use App\Features\Shared\Domain\ValueObjects\StoredThumbnail;

/**
 * The card thumbnail pair as the wire carries it: the 1x RustFS path
 * and the 2x path. The frontend prefixes both with `/assets/` and
 * renders them as a `srcset` (`1x` / `2x`), so the browser picks the
 * variant for the device pixel ratio. Immutable.
 */
final readonly class ThumbnailDto
{
    public function __construct(
        public string $path,
        public string $retinaPath,
    ) {
    }

    public static function fromStored(?StoredThumbnail $thumbnail): ?self
    {
        return $thumbnail === null ? null : new self($thumbnail->url, $thumbnail->retinaUrl);
    }

    /**
     * Builds the pair from the image's source path and the stored 1x
     * thumbnail path — the 2x path follows the {@see \App\Features\Shared\Domain\ValueObjects\ThumbnailPath}
     * convention.
     */
    public static function fromSource(string $sourcePath, ?string $thumbnailPath): ?self
    {
        $thumbnail = StoredThumbnail::fromSource($sourcePath, $thumbnailPath);

        return $thumbnail === null ? null : new self($thumbnail->url, $thumbnail->retinaUrl);
    }

    /**
     * @return array{path: string, retina_path: string}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'retina_path' => $this->retinaPath,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Shared\App\Contracts\AssetStorageInterface;
use App\Features\Shared\App\Contracts\ImageProcessorInterface;
use App\Features\Shared\App\Contracts\ThumbnailGeneratorInterface;
use App\Features\Shared\App\DTOs\ThumbnailDto;
use App\Features\Shared\Domain\ValueObjects\ThumbnailPath;
use App\Features\Shared\Infra\Exceptions\InvalidUploadedImageException;
use Illuminate\Support\Facades\Config;

/**
 * Stores the two card thumbnail variants of an already stored asset: the
 * 1x box from `portfolio.thumbnail` and the 2x box at twice the size. The
 * source bytes are decoded once per variant; the encoded objects are
 * written next to the source under the {@see ThumbnailPath} convention.
 * A missing object and undecodable bytes return null — the caller keeps
 * the full-size fallback.
 */
final class StoredImageThumbnailer implements ThumbnailGeneratorInterface
{
    private const RETINA_SCALE = 2;

    public function __construct(
        private readonly AssetStorageInterface $storage,
        private readonly ImageProcessorInterface $images,
    ) {
    }

    public function thumbnailFor(string $sourcePath): ?ThumbnailDto
    {
        if (! $this->storage->exists($sourcePath)) {
            return null;
        }

        try {
            return $this->generate($sourcePath);
        } catch (InvalidUploadedImageException) {
            return null;
        }
    }

    private function generate(string $sourcePath): ThumbnailDto
    {
        $contents = $this->storage->read($sourcePath);
        $width = (int) Config::get('portfolio.thumbnail.width');
        $height = (int) Config::get('portfolio.thumbnail.height');
        $quality = (int) Config::get('portfolio.thumbnail.quality');

        $paths = ThumbnailPath::forSource($sourcePath);

        $this->storage->write(
            $paths->toStandard(),
            $this->images->toThumbnail($contents, $width, $height, $quality),
        );
        $this->storage->write(
            $paths->toRetina(),
            $this->images->toThumbnail(
                $contents,
                $width * self::RETINA_SCALE,
                $height * self::RETINA_SCALE,
                $quality,
            ),
        );

        return new ThumbnailDto($paths->toStandard(), $paths->toRetina());
    }
}

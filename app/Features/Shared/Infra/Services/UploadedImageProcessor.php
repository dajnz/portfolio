<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Shared\App\Contracts\ImageProcessorInterface;
use App\Features\Shared\Infra\Exceptions\InvalidUploadedImageException;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Exceptions\ImageDecoderException;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * WebP conversion pipeline for uploaded images. Decodes the file (the GD
 * driver applies the EXIF auto-orientation while decoding), scales the image
 * down so its largest side does not exceed the configured limit, and encodes a
 * metadata-free WebP at 85% quality — GD's WebP encoder writes no EXIF/IPTC/XMP,
 * so re-encoding strips all embedded metadata.
 *
 * It also encodes the card thumbnails: a 16:9 center cover crop of an already
 * stored object, downscaled to the requested box without upscaling.
 */
final class UploadedImageProcessor implements ImageProcessorInterface
{
    private const WEBP_QUALITY = 85;

    public function __construct(
        private readonly ImageManager $images,
    ) {
    }

    public function toWebp(string $filePath, int $maxDimension): string
    {
        $image = $this->decode($filePath);

        $image->scaleDown($maxDimension, $maxDimension);

        return $image
            ->encode(new WebpEncoder(quality: self::WEBP_QUALITY, strip: true))
            ->toString();
    }

    /**
     * The card thumbnail: a 16:9 center cover crop, downscaled only (a
     * source narrower than the box keeps its width).
     */
    public function toThumbnail(string $contents, int $width, int $height, int $quality): string
    {
        $image = $this->decodeContents($contents);

        return $image
            ->coverDown($width, $height)
            ->encode(new WebpEncoder(quality: $quality, strip: true))
            ->toString();
    }

    private function decode(string $filePath): ImageInterface
    {
        try {
            return $this->images->decodePath($filePath);
        } catch (ImageDecoderException $exception) {
            throw new InvalidUploadedImageException(previous: $exception);
        }
    }

    private function decodeContents(string $contents): ImageInterface
    {
        try {
            return $this->images->decodeBinary($contents);
        } catch (ImageDecoderException $exception) {
            throw new InvalidUploadedImageException(previous: $exception);
        }
    }
}

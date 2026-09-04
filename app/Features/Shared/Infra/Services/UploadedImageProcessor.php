<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Shared\Infra\Exceptions\InvalidUploadedImageException;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Exceptions\ImageDecoderException;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * WebP conversion pipeline for uploaded images (content-management.md
 * "Image Processing"). Decodes the file (the GD driver applies the EXIF
 * auto-orientation while decoding), scales the image down so its largest
 * side does not exceed the configured limit, and encodes a metadata-free
 * WebP at 85% quality — GD's WebP encoder writes no EXIF/IPTC/XMP, so
 * re-encoding strips all embedded metadata.
 */
final class UploadedImageProcessor
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

    private function decode(string $filePath): ImageInterface
    {
        try {
            return $this->images->decodePath($filePath);
        } catch (ImageDecoderException $exception) {
            throw new InvalidUploadedImageException(previous: $exception);
        }
    }
}

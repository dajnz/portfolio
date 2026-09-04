<?php

declare(strict_types=1);

namespace Tests\Support;

use GdImage;

/**
 * Runtime-generated raster fixtures for the image-processing tests: the
 * bytes are produced by GD (no committed binaries). The EXIF variant
 * injects a minimal APP1/Exif segment (little-endian TIFF with a single
 * IFD0 `Orientation` SHORT entry) right after the JPEG SOI marker.
 */
final class ImageFixtures
{
    public static function png(int $width, int $height, bool $alpha = false): string
    {
        $image = self::canvas($width, $height, $alpha);

        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();


        return $bytes;
    }

    public static function jpeg(int $width, int $height): string
    {
        $image = self::canvas($width, $height);

        ob_start();
        imagejpeg($image);
        $bytes = (string) ob_get_clean();


        return $bytes;
    }

    /**
     * A JPEG whose EXIF `Orientation` tag is set to the given value
     * (6 = rotate 90° clockwise for display).
     */
    public static function jpegWithOrientation(int $width, int $height, int $orientation): string
    {
        $jpeg = self::jpeg($width, $height);

        return substr($jpeg, 0, 2).self::exifApp1Segment($orientation).substr($jpeg, 2);
    }

    public static function webp(int $width, int $height): string
    {
        $image = self::canvas($width, $height);

        ob_start();
        imagewebp($image, null, 85);
        $bytes = (string) ob_get_clean();


        return $bytes;
    }

    public static function avif(int $width, int $height): string
    {
        $image = self::canvas($width, $height);

        ob_start();
        imageavif($image, null, 85);
        $bytes = (string) ob_get_clean();


        return $bytes;
    }

    public static function gif(int $width, int $height): string
    {
        $image = self::canvas($width, $height);

        ob_start();
        imagegif($image);
        $bytes = (string) ob_get_clean();


        return $bytes;
    }

    private static function canvas(int $width, int $height, bool $alpha = false): GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, 40, 90, 160);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $color);

        if ($alpha) {
            imagesavealpha($image, true);
            imagealphablending($image, false);
            imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        }

        return $image;
    }

    private static function exifApp1Segment(int $orientation): string
    {
        $ifd0 = pack('v', 1);
        $ifd0 .= pack('v', 0x0112);
        $ifd0 .= pack('v', 3);
        $ifd0 .= pack('V', 1);
        $ifd0 .= pack('v', $orientation)."\0\0";
        $ifd0 .= pack('V', 0);

        $payload = "Exif\0\0"."II\x2A\x00".pack('V', 8).$ifd0;
        $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

        return $segment;
    }
}

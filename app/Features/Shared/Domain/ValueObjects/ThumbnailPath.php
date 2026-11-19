<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * The stored objects of one image: the source object and its card
 * thumbnail variants. The variants follow the convention
 * `{source without extension}.thumb.webp` (1x) and
 * `{source without extension}.thumb@2x.webp` (2x), so generation,
 * deletion and the orphan sweep derive the same names without extra
 * columns.
 */
final readonly class ThumbnailPath
{
    private const STANDARD_SUFFIX = '.thumb.webp';

    private const RETINA_SUFFIX = '.thumb@2x.webp';

    private function __construct(
        private string $source,
        private string $standard,
        private string $retina,
    ) {
        if ($source === '') {
            throw new InvalidArgumentException('Thumbnail source path must not be empty.');
        }
    }

    public static function forSource(string $sourcePath): self
    {
        $base = self::withoutExtension($sourcePath);

        return new self(
            source: $sourcePath,
            standard: $base.self::STANDARD_SUFFIX,
            retina: $base.self::RETINA_SUFFIX,
        );
    }

    public function toStandard(): string
    {
        return $this->standard;
    }

    public function toRetina(): string
    {
        return $this->retina;
    }

    /**
     * The source and both variants — the asset cleaner input of the
     * delete flows.
     *
     * @return string[]
     */
    public function toFiles(): array
    {
        return [$this->source, $this->standard, $this->retina];
    }

    private static function withoutExtension(string $sourcePath): string
    {
        $dotPosition = strrpos($sourcePath, '.');
        $slashPosition = strrpos($sourcePath, '/');

        if ($dotPosition === false || ($slashPosition !== false && $dotPosition < $slashPosition)) {
            return $sourcePath;
        }

        return substr($sourcePath, 0, $dotPosition);
    }
}

<?php

declare(strict_types=1);

namespace App\Features\Shared\App\UseCases;

use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Shared\App\Contracts\AssetStorageInterface;
use App\Features\Shared\App\Contracts\ImageProcessorInterface;
use App\Features\Shared\App\Contracts\UploadDefaultsInterface;
use App\Features\Shared\App\DTOs\UploadAssetDto;
use App\Features\Shared\App\DTOs\UploadedAssetDto;
use App\Features\Shared\Domain\RepositoryInterfaces\SettingsReadRepositoryInterface;
use InvalidArgumentException;

final class UploadAssetUseCase
{
    private const ENTITY_TYPES = ['blog', 'projects', 'about'];

    private const PROCESSABLE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];

    private const EXTENSIONS_BY_MIME_TYPE = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
        'application/pdf' => 'pdf',
    ];

    public function __construct(
        private readonly ImageProcessorInterface $images,
        private readonly AssetStorageInterface $storage,
        private readonly SettingsReadRepositoryInterface $settings,
        private readonly UploadDefaultsInterface $defaults,
    ) {
    }

    public function upload(UploadAssetDto $asset): UploadedAssetDto
    {
        $this->assertKnownEntityType($asset->entityType);

        $path = $this->isProcessable($asset->mimeType)
            ? $this->storeProcessed($asset)
            : $this->storeOriginal($asset);

        return new UploadedAssetDto(
            path: $path,
            originalName: $asset->originalName,
            size: $this->storage->size($path),
        );
    }

    private function storeProcessed(UploadAssetDto $asset): string
    {
        $path = $this->pathFor($asset->entityType, 'webp');

        $this->storage->write(
            $path,
            $this->images->toWebp($asset->sourcePath, $this->maxDimension()),
        );

        return $path;
    }

    private function storeOriginal(UploadAssetDto $asset): string
    {
        $path = $this->pathFor($asset->entityType, $this->extensionFor($asset->mimeType));

        $this->storage->writeFile($path, $asset->sourcePath);

        return $path;
    }

    private function pathFor(string $entityType, string $extension): string
    {
        return $entityType.'/'.bin2hex(random_bytes(20)).'.'.$extension;
    }

    private function extensionFor(string $mimeType): string
    {
        return self::EXTENSIONS_BY_MIME_TYPE[$mimeType]
            ?? throw new InvalidArgumentException("Unsupported upload MIME type `{$mimeType}`.");
    }

    private function maxDimension(): int
    {
        return (int) $this->settings->value(
            SettingName::ImageMaxDimension->value,
            $this->defaults->imageMaxDimension(),
        );
    }

    private function isProcessable(string $mimeType): bool
    {
        return in_array($mimeType, self::PROCESSABLE_MIME_TYPES, true);
    }

    private function assertKnownEntityType(string $entityType): void
    {
        if (! in_array($entityType, self::ENTITY_TYPES, true)) {
            throw new InvalidArgumentException("Unsupported upload entity type `{$entityType}`.");
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Controllers;

use App\Features\Shared\App\DTOs\UploadAssetDto;
use App\Features\Shared\App\UseCases\DeleteAssetUseCase;
use App\Features\Shared\App\UseCases\UploadAssetUseCase;
use App\Features\Shared\Infra\Exceptions\InvalidUploadedImageException;
use App\Features\Shared\Infra\Requests\UploadDeleteRequest;
use App\Features\Shared\Infra\Requests\UploadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * `/api/upload` — GET-less, auth-required file lifecycle endpoints. Files are
 * stored on the `rustfs` disk under an entity-type prefix with a randomized
 * name; the returned `path` is stored in the appropriate DB column by the
 * caller. Deletion of the previous file on replacement is handled by the entity
 * controllers, which use this same endpoint for direct removals.
 */
final class UploadController
{
    public function __construct(
        private readonly UploadAssetUseCase $uploadAsset,
        private readonly DeleteAssetUseCase $deleteAsset,
    ) {
    }

    public function store(UploadRequest $request): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $entityType = (string) $request->validated('entity_type');

        try {
            $asset = $this->uploadAsset->upload($this->assetFrom($file, $entityType));
        } catch (InvalidUploadedImageException) {
            throw ValidationException::withMessages(['file' => 'The file must be a valid image.']);
        } catch (Throwable $exception) {
            Log::error('Upload failed', [
                'file_name' => $file->getClientOriginalName(),
                'bucket' => Config::get('filesystems.disks.rustfs.bucket'),
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return Response::json([
            'data' => [
                'path' => $asset->path,
                'url' => rtrim((string) Config::get('app.url'), '/').'/assets/'.$asset->path,
                'name' => $asset->originalName,
                'size' => $asset->size,
            ],
        ]);
    }

    public function destroy(UploadDeleteRequest $request): JsonResponse
    {
        $deleted = $this->deleteAsset->delete((string) $request->json()->get('path'));

        if (! $deleted) {
            return Response::json(['message' => 'File not found.'], 404);
        }

        return Response::json();
    }

    private function assetFrom(UploadedFile $file, string $entityType): UploadAssetDto
    {
        return new UploadAssetDto(
            sourcePath: (string) $file->getRealPath(),
            originalName: $file->getClientOriginalName(),
            mimeType: (string) $file->getMimeType(),
            entityType: $entityType,
        );
    }
}

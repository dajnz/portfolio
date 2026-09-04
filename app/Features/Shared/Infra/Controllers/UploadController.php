<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Controllers;

use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Shared\Domain\RepositoryInterfaces\SettingsReadRepositoryInterface;
use App\Features\Shared\Infra\Exceptions\InvalidUploadedImageException;
use App\Features\Shared\Infra\Requests\UploadDeleteRequest;
use App\Features\Shared\Infra\Requests\UploadRequest;
use App\Features\Shared\Infra\Services\UploadedImageProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * `/api/upload` — GET-less, auth-required file lifecycle endpoints
 * (content-management.md:773-819). Files are stored on the `rustfs` disk
 * under an entity-type prefix with a randomized name; the returned `path`
 * is stored in the appropriate DB column by the caller. Deletion of the
 * previous file on replacement is handled by the entity controllers
 * (steps 4.1-8.1), which use this same endpoint for direct removals.
 *
 * Images (`jpeg/png/webp/avif`) pass through the WebP processing pipeline
 * before storage — EXIF auto-orientation, downscale to the configured
 * largest-side limit, re-encode at 85% with all metadata stripped. PDFs
 * (resume upload) and GIFs (animation preservation) are stored as-uploaded.
 */
final class UploadController
{
    private const PROCESSABLE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];

    public function __construct(
        private readonly UploadedImageProcessor $processor,
        private readonly SettingsReadRepositoryInterface $settings,
    ) {
    }

    public function store(UploadRequest $request): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->validated('file');
        $entityType = $request->validated('entity_type');

        $path = $this->isProcessableImage($file)
            ? $this->storeProcessed($entityType, $file)
            : $this->storeAsUploaded($entityType, $file);

        return response()->json([
            'data' => [
                'path' => $path,
                'url' => rtrim((string) config('app.url'), '/').'/assets/'.$path,
                'name' => $file->getClientOriginalName(),
                'size' => Storage::disk('rustfs')->size($path),
            ],
        ]);
    }

    public function destroy(UploadDeleteRequest $request): JsonResponse
    {
        $path = (string) $request->json()->get('path');

        if (! Storage::disk('rustfs')->exists($path)) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        Storage::disk('rustfs')->delete($path);

        return response()->json();
    }

    private function storeProcessed(string $entityType, UploadedFile $file): string
    {
        try {
            $bytes = $this->processor->toWebp((string) $file->getRealPath(), $this->maxImageDimension());
        } catch (InvalidUploadedImageException) {
            throw ValidationException::withMessages(['file' => 'The file must be a valid image.']);
        }

        $path = $entityType.'/'.Str::random(40).'.webp';

        Storage::disk('rustfs')->put($path, $bytes);

        return $path;
    }

    private function storeAsUploaded(string $entityType, UploadedFile $file): string
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $name = Str::random(40).'.'.$extension;

        Storage::disk('rustfs')->putFileAs($entityType, $file, $name);

        return $entityType.'/'.$name;
    }

    private function isProcessableImage(UploadedFile $file): bool
    {
        return in_array((string) $file->getMimeType(), self::PROCESSABLE_MIME_TYPES, true);
    }

    private function maxImageDimension(): int
    {
        return (int) $this->settings->value(
            SettingName::ImageMaxDimension->value,
            config('portfolio.image_max_dimension'),
        );
    }
}

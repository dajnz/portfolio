<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Controllers;

use App\Features\Shared\Infra\Requests\UploadDeleteRequest;
use App\Features\Shared\Infra\Requests\UploadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * `/api/upload` — GET-less, auth-required file lifecycle endpoints
 * (content-management.md:773-819). Files are stored on the `rustfs` disk
 * under an entity-type prefix with a randomized name; the returned `path`
 * is stored in the appropriate DB column by the caller. Deletion of the
 * previous file on replacement is handled by the entity controllers
 * (steps 4.1-8.1), which use this same endpoint for direct removals.
 */
final class UploadController
{
    public function store(UploadRequest $request): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->validated('file');
        $entityType = $request->validated('entity_type');

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $name = Str::random(40).'.'.$extension;

        Storage::disk('rustfs')->putFileAs($entityType, $file, $name);

        $path = $entityType.'/'.$name;

        return response()->json([
            'data' => [
                'path' => $path,
                'url' => rtrim((string) config('app.url'), '/').'/assets/'.$path,
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
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
}

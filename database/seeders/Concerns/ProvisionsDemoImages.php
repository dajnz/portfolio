<?php

declare(strict_types=1);

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Uploads committed demo image stubs to the RustFS `portfolio` bucket
 * under fixed keys. The seeder stores the keys in the DB regardless; the
 * public surfaces resolve them through `/assets/{key}`. Under unit tests
 * no object storage is touched, and when RustFS is unreachable the
 * failure is logged instead of aborting the seed.
 */
trait ProvisionsDemoImages
{
    protected function provisionDemoImage(string $key, string $sourceFile, string $contentType): bool
    {
        if (app()->runningUnitTests()) {
            return false;
        }

        $source = database_path('seeders/assets/'.$sourceFile);

        try {
            Storage::disk('rustfs')->put(
                $key,
                (string) file_get_contents($source),
                ['ContentType' => $contentType],
            );
        } catch (Throwable $exception) {
            Log::warning('Could not upload a demo image to RustFS.', [
                'key' => $key,
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }

        return true;
    }
}

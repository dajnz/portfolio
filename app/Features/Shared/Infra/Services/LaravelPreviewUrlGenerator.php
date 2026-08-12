<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Shared\App\Contracts\PreviewUrlGeneratorInterface;
use Illuminate\Support\Facades\URL;

/**
 * The Laravel-backed {@see PreviewUrlGeneratorInterface}: a 1-hour
 * temporary signed route URL (routes.md:151 — the admin form's preview
 * button opens it in a new tab).
 */
final class LaravelPreviewUrlGenerator implements PreviewUrlGeneratorInterface
{
    public function temporarySigned(string $routeName, int $expiresInSeconds, array $parameters): string
    {
        return URL::temporarySignedRoute(
            $routeName,
            now()->addSeconds($expiresInSeconds),
            $parameters,
        );
    }
}

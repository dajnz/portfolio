<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Middleware;

use App\Features\Shared\App\Contracts\LoggerInterface;
use App\Features\Shared\Infra\Services\PublicPageCache;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs successful admin content changes and invalidates the cached public
 * pages. The request-level entry covers every admin mutation
 * (create/update/delete/restore/bulk/publish) in one place; the
 * authentication flows are excluded — they log their own dedicated events
 * (login/TOTP) and change no content.
 */
final class LogAdminMutationsMiddleware
{
    private const AUTH_PATH_PATTERNS = [
        'admin/login*',
        'admin/logout*',
        'admin/totp*',
        'admin/github*',
    ];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PublicPageCache $publicPageCache,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldLog($request, $response)) {
            $this->publicPageCache->flush();

            $this->logger->info('Admin content change', [
                'admin_id' => Auth::id(),
                'method' => $request->method(),
                'path' => $request->path(),
            ]);
        }

        return $response;
    }

    private function shouldLog(Request $request, Response $response): bool
    {
        if ($request->isMethod('GET') || $response->getStatusCode() >= 400) {
            return false;
        }

        if (! $request->is('admin/*') || ! Auth::check()) {
            return false;
        }

        foreach (self::AUTH_PATH_PATTERNS as $pattern) {
            if ($request->is($pattern)) {
                return false;
            }
        }

        return true;
    }
}

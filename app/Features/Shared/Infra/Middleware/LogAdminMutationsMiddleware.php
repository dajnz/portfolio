<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Middleware;

use App\Features\Shared\App\Contracts\LoggerInterface;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs successful admin content changes (error-handling.md:226-240 —
 * "Content CRUD | info | stack | Admin ID, entity type, action"). The
 * request-level entry covers every admin mutation (create/update/
 * delete/restore/bulk/publish) in one place; the authentication flows
 * are excluded — they log their own dedicated events (login/TOTP).
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
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldLog($request, $response)) {
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

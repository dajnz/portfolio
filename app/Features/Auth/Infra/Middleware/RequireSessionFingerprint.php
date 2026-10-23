<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Middleware;

use App\Features\Auth\App\Contracts\SessionFingerprintInterface;
use App\Features\Auth\Infra\Services\AdminAuthService;
use App\Features\Shared\App\Contracts\LoggerInterface;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpFoundation\Response;

/**
 * `auth.fingerprint` middleware alias: compares the keyed client
 * fingerprint stored at login against the current request. A missing or
 * different fingerprint means the session cookie is being replayed from
 * another client — the session is destroyed server-side and the request is
 * sent back to the login page (or answered with a 401 on the API surface).
 */
final class RequireSessionFingerprint
{
    public function __construct(
        private readonly AdminAuthService $auth,
        private readonly SessionFingerprintInterface $fingerprint,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! Config::get('session.binding.enabled', true)) {
            return $next($request);
        }

        $expected = $this->fingerprint->for((string) $request->ip(), $request->userAgent());

        if (! $this->auth->isAuthenticatedAdmin() || $this->auth->fingerprintMatches($expected)) {
            return $next($request);
        }

        $this->logger->warning('Session fingerprint mismatch', [
            'admin_id' => Auth::guard('web')->id(),
            'request_id' => $request->attributes->get('request_id'),
        ]);

        $this->auth->forceLogoutAndInvalidate();

        if ($request->is('api/*')) {
            throw new AuthenticationException();
        }

        return Redirect::route('login')->with('notification', [
            'type' => 'error',
            'message' => __('auth.session_expired'),
        ]);
    }
}

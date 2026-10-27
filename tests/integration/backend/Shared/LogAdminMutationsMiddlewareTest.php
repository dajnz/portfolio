<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Shared;

use App\Features\Shared\App\Contracts\LoggerInterface;
use App\Features\Shared\Infra\Middleware\LogAdminMutationsMiddleware;
use App\Features\Shared\Infra\Services\PublicPageCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Symfony\Component\HttpFoundation\Response;

describe('LogAdminMutationsMiddleware', function (): void {
    it('logs successful admin content changes (error-handling.md:226-240)', function (): void {
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('id')->andReturn(7);

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info')->once()->with(
            'Admin content change',
            Mockery::on(fn (array $context): bool => $context['admin_id'] === 7
                && $context['method'] === 'PUT'
                && $context['path'] === 'admin/contacts/5'),
        );

        $middleware = new LogAdminMutationsMiddleware($logger, new PublicPageCache());

        $response = $middleware->handle(
            Request::create('/admin/contacts/5', 'PUT'),
            fn (): Response => new Response('', 302),
        );

        expect($response->getStatusCode())->toBe(302);
    });

    it('skips reads, guests, auth flows and failed responses', function (): void {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldNotReceive('info');

        $middleware = new LogAdminMutationsMiddleware($logger, new PublicPageCache());
        $next = fn (): Response => new Response('', 302);

        // `check()` is consulted for the two auth-flow requests, then for
        // the guest mutation (the read/422/non-admin calls short-circuit).
        Auth::shouldReceive('check')->andReturn(true, true, false);

        $middleware->handle(Request::create('/admin/contacts', 'GET'), fn (): Response => new Response('', 200));
        $middleware->handle(Request::create('/admin/login', 'POST'), $next);
        $middleware->handle(Request::create('/admin/totp/verify', 'POST'), $next);

        // Non-admin paths are not content changes.
        $middleware->handle(Request::create('/api/upload', 'POST'), fn (): Response => new Response('', 201));

        // Failed mutations are not logged.
        $middleware->handle(Request::create('/admin/contacts', 'POST'), fn (): Response => new Response('', 422));

        // Guests are not logged.
        $middleware->handle(Request::create('/admin/contacts', 'POST'), $next);
    });
});

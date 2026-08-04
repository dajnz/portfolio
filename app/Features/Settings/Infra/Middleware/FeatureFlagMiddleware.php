<?php

declare(strict_types=1);

namespace App\Features\Settings\Infra\Middleware;

use App\Features\Settings\Domain\Enums\FeatureFlagName;
use App\Features\Settings\Domain\RepositoryInterfaces\FeatureFlagRepositoryInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Feature-gates routes via the `feature:{flag_key}` middleware alias
 * (e.g. `feature:github_oauth`). Unknown or disabled flags abort with
 * 404 — the associated UI is hidden, so the endpoint must not be
 * reachable.
 */
final class FeatureFlagMiddleware
{
    public function __construct(
        private readonly FeatureFlagRepositoryInterface $repository,
    ) {
    }

    public function handle(Request $request, Closure $next, string $flag): Response
    {
        $name = FeatureFlagName::tryFrom($flag);

        if ($name === null || ! $this->repository->isEnabled($name)) {
            abort(404);
        }

        return $next($request);
    }
}

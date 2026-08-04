<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Settings;

use App\Features\Settings\Domain\Enums\FeatureFlagName;
use App\Features\Settings\Domain\RepositoryInterfaces\FeatureFlagRepositoryInterface;
use App\Features\Settings\Infra\Middleware\FeatureFlagMiddleware;
use App\Features\Settings\Infra\Models\FeatureFlagModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

describe('FeatureFlagMiddleware', function (): void {
    it('lets requests through when the flag is enabled', function (): void {
        FeatureFlagModel::query()->create(['key' => 'social_reactions', 'enabled' => true]);

        $middleware = new FeatureFlagMiddleware(app(FeatureFlagRepositoryInterface::class));

        $response = $middleware->handle(
            Request::create('/api/test'),
            fn ($request): Response => response('ok'),
            'social_reactions',
        );

        expect($response->getStatusCode())->toBe(200);
    });

    it('aborts with 404 when the flag is disabled', function (): void {
        FeatureFlagModel::query()->create(['key' => 'social_reactions', 'enabled' => false]);

        $middleware = new FeatureFlagMiddleware(app(FeatureFlagRepositoryInterface::class));

        $middleware->handle(
            Request::create('/api/test'),
            fn ($request): Response => response('ok'),
            'social_reactions',
        );
    })->throws(NotFoundHttpException::class);

    it('aborts with 404 for a missing flag', function (): void {
        $middleware = new FeatureFlagMiddleware(app(FeatureFlagRepositoryInterface::class));

        $middleware->handle(
            Request::create('/api/test'),
            fn ($request): Response => response('ok'),
            'unknown_flag',
        );
    })->throws(NotFoundHttpException::class);

    it('works through the feature: middleware alias on a route', function (): void {
        Route::get('/feature-route-test', fn () => response('ok'))->middleware('feature:social_reactions');

        $this->get('/feature-route-test')->assertNotFound();

        // Toggling through the repository (not a raw model write) keeps the
        // per-flag cache coherent.
        app(FeatureFlagRepositoryInterface::class)->update(FeatureFlagName::SocialReactions, true);

        $this->get('/feature-route-test')->assertOk();
    });
});

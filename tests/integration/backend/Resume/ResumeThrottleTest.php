<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Resume;

use Illuminate\Support\Facades\Route;

describe('ResumeThrottle', function (): void {
    it('rate-limits the public resume download', function (): void {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($route): bool => $route->uri() === 'cv');

        expect($route)->not->toBeNull()
            ->and($route->gatherMiddleware())->toContain('throttle:resume');
    });
});

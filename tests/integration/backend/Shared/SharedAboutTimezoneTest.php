<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Shared;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Traits\SeedsPortfolioData;

uses(SeedsPortfolioData::class);

describe('Shared about timezone', function (): void {
    it('shares the owner timezone from the about row', function (): void {
        $this->seedLandingDataset();

        $this->get('/admin/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('about_timezone', 'Europe/Berlin'));
    });

    it('falls back to UTC when no about row exists', function (): void {
        $this->get('/admin/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('about_timezone', 'UTC'));
    });
});

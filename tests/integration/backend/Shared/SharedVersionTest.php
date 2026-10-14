<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Shared;

use Database\Seeders\SettingsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Traits\SeedsPortfolioData;

uses(SeedsPortfolioData::class);

beforeEach(function (): void {
    $this->seed(SettingsSeeder::class);
});

describe('Shared version props', function (): void {
    it('ships the configured app version on every page', function (): void {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('version', '0.0.0-test'));
    });
});

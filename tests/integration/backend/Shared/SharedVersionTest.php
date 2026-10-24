<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Shared;

use Database\Seeders\SettingsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Traits\InteractsWithAdmin;
use Tests\Support\Traits\SeedsPortfolioData;

uses(SeedsPortfolioData::class, InteractsWithAdmin::class);

beforeEach(function (): void {
    $this->seed(SettingsSeeder::class);
});

describe('Shared version props', function (): void {
    it('does not expose the app version on public pages', function (): void {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->missing('version'));
    });

    it('ships the configured app version on authenticated admin pages', function (): void {
        $admin = $this->createAdmin();
        $this->loginAsAdmin($admin);

        $this->get('/admin/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('version', '0.0.0-test'));
    });
});

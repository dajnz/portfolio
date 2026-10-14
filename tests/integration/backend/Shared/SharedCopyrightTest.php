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

describe('Shared copyright props', function (): void {
    it('ships the locale-resolved owner name on every page', function (): void {
        $this->seedLandingDataset();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('copyright.owner_name', 'Alex Kovalev'));
    });

    it('resolves the owner name to the request locale', function (): void {
        $this->seedLandingDataset();

        $this->withUnencryptedCookie('locale', 'ru')
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('copyright.owner_name', 'Алекс Ковалев'));
    });

    it('ships an empty owner name when no about row exists', function (): void {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('copyright.owner_name', ''));
    });
});

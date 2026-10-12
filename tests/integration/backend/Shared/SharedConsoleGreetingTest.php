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

describe('Shared console greeting', function (): void {
    it('ships the greeting props on the landing page', function (): void {
        $this->seedLandingDataset();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('console_greeting.greeting', 'Wow, interested enough to open dev console? Contact me:')
                ->where('console_greeting.name', 'Alex Kovalev')
                ->where('console_greeting.position', 'Full-Stack Developer')
                ->where('console_greeting.contacts.0.label', 'GitHub')
                ->where('console_greeting.contacts.0.value', 'octocat'));
    });

    it('ships the greeting props on every other public page', function (): void {
        $this->seedLandingDataset();

        $this->get('/blog')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Public/Pages/BlogIndexPage')
                ->where('console_greeting.greeting', 'Wow, interested enough to open dev console? Contact me:')
                ->where('console_greeting.name', 'Alex Kovalev')
                ->where('console_greeting.position', 'Full-Stack Developer')
                ->where('console_greeting.contacts.0.label', 'GitHub')
                ->where('console_greeting.contacts.0.value', 'octocat'));
    });
});

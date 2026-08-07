<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Portfolio;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Traits\InteractsWithAdmin;
use Tests\Support\Traits\SeedsPortfolioData;

uses(InteractsWithAdmin::class, SeedsPortfolioData::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingsSeeder::class);
});

describe('LandingController', function (): void {
    it('renders the landing page with all public props', function (): void {
        $this->seedLandingDataset();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portfolio/Public/Pages/LandingPage')
                ->where('locale', 'en')
                ->where('theme', 'light')
                ->where('auth.user', null)
                ->where('about.name', 'Alex Kovalev')
                ->where('about.title', 'Full-Stack Developer')
                ->where('about.short_pitch', 'I build fast web apps.')
                ->where('about.photo', 'about/photo.jpg')
                ->where('about.photo_alt.en', 'Portrait')
                ->where('about.location', 'Berlin')
                ->where('about.show_location', true)
                ->where('about.timezone', 'Europe/Berlin')
                ->where('about.availability_status', 'available')
                ->where('about.work_type', 'hybrid')
                ->where('about.relocation_readiness', true)
                ->has('skills', 1)
                ->where('skills.0.name', 'Backend')
                ->where('skills.0.icon', 'heroicons:server')
                ->where('skills.0.skills.0.name', 'Laravel')
                ->where('skills.0.skills.0.proficiency', 5)
                ->has('featured_projects', 2)
                ->where('featured_projects.0.title', 'Project 2')
                ->where('featured_projects.1.title', 'Project 1')
                ->where('featured_projects.0.tech_tags', [])
                ->where('has_more', true)
                ->has('contacts', 1)
                ->where('contacts.0.name', 'GitHub')
                ->where('contacts.0.value', 'octocat')
                ->where('contacts.0.icon', 'simple-icons:github')
                ->where('contacts.0.url', 'https://github.com/alex')
                ->where('seo.title', 'Alex Kovalev — Full-Stack Developer')
                ->where('seo.description', 'I build fast web apps.')
                ->where('seo.og_type', 'website')
                ->has('seo.json_ld', 2)
                ->where('seo.json_ld.0.@type', 'Person')
                ->where('seo.json_ld.1.@type', 'WebSite')
                ->where('console_greeting.greeting', 'Hey there! Welcome to my portfolio.')
                ->where('console_greeting.name', 'Alex Kovalev')
                ->where('console_greeting.position', 'Full-Stack Developer')
                ->where('console_greeting.contacts.0.label', 'GitHub')
                ->where('console_greeting.contacts.0.value', 'octocat'));
    });

    it('resolves the props per the {locale} path param', function (): void {
        $this->seedLandingDataset();

        $this->get('/ru')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'ru')
                ->where('about.name', 'Алекс Ковалев')
                ->where('about.location', 'Берлин')
                ->where('contacts.0.name', 'Гитхаб')
                ->where('seo.title', 'Алекс Ковалев — Fullstack-разработчик'));
    });

    it('embeds the session-derived honeypot field name in the landing props', function (): void {
        $this->seedLandingDataset();

        $response = $this->get('/');

        $expected = substr(
            hash_hmac('sha256', 'honeypot_'.session()->getId(), (string) config('app.key')),
            0,
            32,
        );

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('feedback_honeypot_name', $expected)
                ->where('feedback_honeypot_name', fn (string $name) => preg_match('/^[0-9a-f]{32}$/', $name) === 1));
    });

    it('rejects an unknown locale prefix with 404', function (): void {
        $this->get('/fr')->assertNotFound();
    });

    it('tolerates an empty dataset', function (): void {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('about', null)
                ->where('skills', [])
                ->where('featured_projects', [])
                ->where('has_more', false)
                ->where('contacts', [])
                ->where('seo.title', ' — Full-Stack Developer'));
    });
});

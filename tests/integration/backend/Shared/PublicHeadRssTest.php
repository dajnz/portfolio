<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Shared;

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingsSeeder::class);
});

/**
 * The RSS auto-discovery `<link rel="alternate" type="application/rss+xml">`
 * rendered by the root view for the public surface only — feed readers
 * and browsers detect the blog feed from any public page.
 */
describe('Public head RSS discovery link', function (): void {
    it('advertises the feed on public pages', function (): void {
        $expected = '<link rel="alternate" type="application/rss+xml" href="'
            .rtrim((string) config('app.url'), '/').'/rss">';

        $this->get('/blog')
            ->assertOk()
            ->assertSee($expected, false);
    });

    it('is omitted from the admin surface', function (): void {
        $this->get('/admin/login')
            ->assertOk()
            ->assertDontSee('application/rss+xml', false);
    });
});

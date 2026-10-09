<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Shared;

use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingsSeeder::class);
});

/**
 * The hreflang `<link rel="alternate">` block every public page renders
 * in the `<head>`: locale-prefixed variants of the slashless canonical
 * URL plus the `x-default`, derived in the root view from the shared
 * `supported_locales` prop. `useSeoUpdater` renders the same set
 * client-side (its unit spec covers the parity).
 */
describe('Public head hreflang alternates', function (): void {
    it('renders locale alternates and the x-default for a nested public path', function (): void {
        $base = rtrim((string) config('app.url'), '/');
        $body = $this->get('/blog')->getContent();

        expect($body)
            ->toContain('<link rel="alternate" hreflang="en" href="'.$base.'/en/blog" data-inertia="">')
            ->toContain('<link rel="alternate" hreflang="ru" href="'.$base.'/ru/blog" data-inertia="">')
            ->toContain('<link rel="alternate" hreflang="x-default" href="'.$base.'/blog" data-inertia="">');
    });

    it('maps the root canonical to slashless locale prefixes', function (): void {
        $base = rtrim((string) config('app.url'), '/');

        $this->get('/')
            ->assertOk()
            ->assertSee('<link rel="alternate" hreflang="en" href="'.$base.'/en" data-inertia="">', false)
            ->assertSee('<link rel="alternate" hreflang="x-default" href="'.$base.'" data-inertia="">', false);
    });

    it('omits disabled locales from the alternates', function (): void {
        DB::table('settings')->where('key', 'supported_locales')->update(['value' => '["en"]']);

        $body = $this->get('/blog')->getContent();

        expect($body)
            ->toContain('hreflang="en"')
            ->toContain('hreflang="x-default"')
            ->not->toContain('hreflang="ru"');
    });
});

<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Shared;

use App\Features\Settings\Infra\Models\SettingModel;

describe('MiddlewareWiring', function (): void {
    it('301-redirects trailing slashes to the slashless version preserving the query string', function (): void {
        // `$this->get()` normalizes the URI (prepareUrlForRequest trims the
        // trailing slash), so the raw request is sent through the kernel.
        $response = app(\Illuminate\Contracts\Http\Kernel::class)->handle(
            \Illuminate\Http\Request::create('/admin/login/?foo=bar&baz=1', 'GET'),
        );

        expect($response->getStatusCode())->toBe(301)
            ->and($response->headers->get('Location'))->toStartWith(url('/admin/login').'?')
            ->and($response->headers->get('Location'))->toContain('foo=bar')
            ->and($response->headers->get('Location'))->toContain('baz=1');
    });

    it('does not redirect the root path', function (): void {
        $this->get('/')->assertOk();
    });

    it('adds the X-Request-ID response header on web and api responses', function (): void {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('X-Request-ID');

        $this->getJson('/api/skills/search?q=php')
            ->assertOk()
            ->assertHeader('X-Request-ID');
    });

    it('sets the Content-Language header from the locale cookie', function (): void {
        $this->withCookie('locale', 'ru')->get('/')->assertHeader('Content-Language', 'ru');

        $this->withCookie('locale', 'de')->get('/')->assertHeader('Content-Language', 'en');
    });

    it('serves 503 for public routes in maintenance mode while /admin/* stays up', function (): void {
        SettingModel::query()->updateOrCreate(
            ['key' => 'site_maintenance_mode'],
            ['value' => '1', 'type' => 'boolean'],
        );

        $this->get('/')->assertStatus(503);
        $this->get('/robots.txt')->assertStatus(503);

        $this->get('/admin/login')->assertOk();
    });

    it('serves normally when maintenance mode is off', function (): void {
        SettingModel::query()->updateOrCreate(
            ['key' => 'site_maintenance_mode'],
            ['value' => '0', 'type' => 'boolean'],
        );

        $this->get('/')->assertOk();
    });
});

<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Shared;

use App\Features\Settings\Infra\Models\SettingModel;
use Tests\Support\Traits\InteractsWithAdmin;

uses(InteractsWithAdmin::class);

describe('SharedProps', function (): void {
    it('shares all documented props on a public Inertia page', function (): void {
        $this->get('/admin/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Admin/Pages/LoginPage')
                ->where('locale', 'en')
                ->where('theme', 'light')
                ->where('auth.user', null)
                ->where('flash.success', null)
                ->where('flash.error', null)
                ->where('notification', null)
                ->where('errors', [])
                ->where('cspNonce', fn (string $nonce) => strlen($nonce) === 24)
                ->where('featureFlags.github_oauth', false)
                ->where('featureFlags.social_reactions', false)
                ->where('featureFlags.console_greeting', false)
                ->where('settings.locale', 'en')
                ->where('settings.theme', 'light')
                ->where('settings.items_per_page', 12)
                ->where('request_id', fn (string $id) => (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id))
                ->has('ziggy.routes'));
    });

    it('reflects the locale and theme cookies in the props and headers', function (): void {
        $this->withUnencryptedCookie('locale', 'ru')
            ->withUnencryptedCookie('theme', 'dark')
            ->get('/admin/login')
            ->assertOk()
            ->assertHeader('Content-Language', 'ru')
            ->assertInertia(fn ($page) => $page
                ->where('locale', 'ru')
                ->where('theme', 'dark')
                ->where('settings.locale', 'ru')
                ->where('settings.theme', 'dark'));
    });

    it('exposes the authenticated admin under auth.user per ADR-0004', function (): void {
        $this->seed(\Database\Seeders\SettingsSeeder::class);
        $admin = $this->createAdmin();
        $this->loginAsAdmin($admin);

        $this->get('/admin/settings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Admin/Pages/SettingsPage')
                ->where('auth.user.id', $admin->id)
                ->where('auth.user.name', $admin->name)
                ->where('auth.user.email', $admin->email)
                ->where('auth.user.login', $admin->login)
                ->where('auth.user.github_linked', false)
                ->where('auth.user.totp_enabled', false)
                ->where('auth.user.is_oauth_primary', false));
    });

    it('surfaces the session flash success and notification', function (): void {
        $this->withSession([
            'success' => 'Saved.',
            'notification' => ['type' => 'success', 'message' => 'Saved.'],
        ])->get('/admin/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('flash.success', 'Saved.')
                ->where('notification', ['type' => 'success', 'message' => 'Saved.']));
    });

    it('reads items_per_page from the settings table with the config fallback', function (): void {
        SettingModel::query()->updateOrCreate(
            ['key' => 'items_per_page'],
            ['value' => '24', 'type' => 'integer'],
        );

        $this->get('/admin/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('settings.items_per_page', 24));
    });
});

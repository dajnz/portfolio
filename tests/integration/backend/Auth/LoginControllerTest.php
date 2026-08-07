<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Auth;

use App\Features\Auth\Infra\Models\AdminModel;
use Illuminate\Support\Facades\Crypt;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Traits\InteractsWithAdmin;

uses(InteractsWithAdmin::class);

describe('LoginController', function (): void {
    it('renders the login page with the GitHub button when the flag is on and an admin is linked', function (): void {
        $this->enableGithubOauthFlag();
        $this->createAdmin(['github_id' => '123']);

        $this->get('/admin/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Admin/Pages/LoginPage')
                ->where('github_login_available', true)
                ->where('loginPage', true));
    });

    it('hides the GitHub button when no admin account is linked', function (): void {
        $this->enableGithubOauthFlag();
        $this->createAdmin();

        $this->get('/admin/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('github_login_available', false));
    });

    it('hides the GitHub button when the flag is off even if an admin is linked', function (): void {
        $this->createAdmin(['github_id' => '123']);

        $this->get('/admin/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('github_login_available', false));
    });

    it('clears stale half-auth keys when the login page is opened', function (): void {
        $this->withSession(['totp.pending_admin_id' => 1, 'totp.pending_until' => now()->addMinutes(5)->getTimestamp()])
            ->get('/admin/login')
            ->assertOk();

        expect(session('totp.pending_admin_id'))->toBeNull();
    });

    it('authenticates with valid credentials when TOTP is disabled', function (): void {
        $this->createAdmin();

        $this->post('/admin/login', [
            'login' => 'admin',
            'password' => self::ADMIN_PASSWORD,
        ])->assertRedirect('/admin');

        $this->assertAuthenticated();
        expect(session('session_version'))->toBe(0);
    });

    it('authenticates via the email field', function (): void {
        $this->createAdmin();

        $this->post('/admin/login', [
            'login' => 'admin@example.com',
            'password' => self::ADMIN_PASSWORD,
        ])->assertRedirect('/admin');

        $this->assertAuthenticated();
    });

    it('rejects invalid credentials and stays unauthenticated', function (): void {
        $this->createAdmin();

        $this->post('/admin/login', [
            'login' => 'admin',
            'password' => 'wrong-password',
        ])->assertRedirect()
            ->assertSessionHasErrors(['login' => 'These credentials do not match our records.']);

        $this->assertGuest();
    });

    it('enters the half-auth state when TOTP is enabled', function (): void {
        $admin = $this->adminWithTotp();

        $this->post('/admin/login', [
            'login' => $admin->login,
            'password' => self::ADMIN_PASSWORD,
        ])->assertRedirect(route('login.totp'));

        $this->assertGuest();
        expect(session('totp.pending_admin_id'))->toBe((int) $admin->id)
            ->and(session('totp.pending_until'))->toBeGreaterThanOrEqual(now()->getTimestamp())
            ->and(session('session_version'))->toBeNull();
    });

    it('rate-limits login attempts to 5 per minute per IP', function (): void {
        $this->createAdmin();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', [
                'login' => 'admin',
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        $this->post('/admin/login', [
            'login' => 'admin',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    });

    it('requires the login and password fields', function (): void {
        $this->post('/admin/login', [])->assertRedirect()
            ->assertSessionHasErrors(['login', 'password']);
    });

    it('logout clears pending half-auth and github-link keys', function (): void {
        $admin = $this->createAdmin();

        $this->loginAsAdmin($admin)
            ->withSession([
                'totp.pending_admin_id' => $admin->id,
                'github_link.pending_admin_id' => $admin->id,
                'github_link.pending_github_id' => '123',
            ])
            ->post('/admin/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
        expect(session('totp.pending_admin_id'))->toBeNull()
            ->and(session('github_link.pending_admin_id'))->toBeNull()
            ->and(session('github_link.pending_github_id'))->toBeNull();
    });

    it('stores the totp secret encrypted at rest', function (): void {
        $this->adminWithTotp();

        $row = AdminModel::query()->firstOrFail();

        expect($row->totp_secret)->not->toBe(self::TOTP_SECRET)
            ->and(Crypt::decryptString($row->totp_secret))->toBe(self::TOTP_SECRET);
    });
});

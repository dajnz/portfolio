<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Auth;

use App\Features\Auth\App\Contracts\SessionFingerprintInterface;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;
use Tests\Support\Traits\InteractsWithAdmin;

uses(InteractsWithAdmin::class);

describe('session fingerprint binding', function (): void {
    it('stores the fingerprint of the current client on login', function (): void {
        $this->createAdmin();

        $this->post('/admin/login', [
            'login' => 'admin',
            'password' => self::ADMIN_PASSWORD,
        ])->assertRedirect('/admin');

        expect(session('auth_fingerprint'))->toBe(
            app(SessionFingerprintInterface::class)->for(
                (string) request()->ip(),
                request()->userAgent(),
            ),
        );
    });

    it('binds the fingerprint when the TOTP login completes', function (): void {
        $admin = $this->adminWithTotp();

        $this->pendingTotpSession($admin)->get('/admin/login/totp')->assertOk();

        $this->post('/admin/login/totp', ['code' => $this->totpCode()])
            ->assertRedirect('/admin');

        expect(session('auth_fingerprint'))->toBe(
            app(SessionFingerprintInterface::class)->for(
                (string) request()->ip(),
                request()->userAgent(),
            ),
        );
    });

    it('allows requests from the same client', function (): void {
        $admin = $this->createAdmin();

        $this->loginAsAdmin($admin)->get('/admin/profile')->assertOk();
        $this->get('/admin/profile')->assertOk();
    });

    it('logs the admin out when the user agent changes', function (): void {
        $admin = $this->createAdmin();

        $this->loginAsAdmin($admin)->get('/admin/profile')->assertOk();
        $boundSessionId = session()->getId();

        $this->withHeader('User-Agent', 'Attacker Browser')
            ->get('/admin/profile')
            ->assertRedirect(route('login'))
            ->assertSessionHas('notification', [
                'type' => 'error',
                'message' => 'Session expired — please log in again.',
            ]);

        $this->assertGuest();
        expect(session('auth_fingerprint'))->toBeNull()
            ->and(session()->getId())->not->toBe($boundSessionId);
    });

    it('logs the admin out when the IPv4 network changes', function (): void {
        $admin = $this->createAdmin();

        $this->loginAsAdmin($admin)->get('/admin/profile')->assertOk();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get('/admin/profile')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    });

    it('allows address changes inside the same IPv4 network', function (): void {
        $admin = $this->createAdmin();

        $this->loginAsAdmin($admin)->get('/admin/profile')->assertOk();

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.53'])
            ->get('/admin/profile')
            ->assertOk();
    });

    it('logs out an authenticated session without a recorded fingerprint', function (): void {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->withSession(['session_version' => (int) $admin->session_version])
            ->get('/admin/profile')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    });

    it('returns 401 for a replayed session on the API surface', function (): void {
        $admin = $this->createAdmin();

        $this->loginAsAdmin($admin)->get('/admin/profile')->assertOk();

        $this->withHeader('User-Agent', 'Attacker Browser')
            ->postJson('/api/tags', ['name' => 'Tag'])
            ->assertUnauthorized();
    });

    it('binds the fingerprint on recaller-based authentication', function (): void {
        $admin = $this->createAdmin();

        Auth::shouldReceive('viaRemember')->once()->andReturn(true);

        event(new Login('web', $admin, false));

        expect(session('auth_fingerprint'))->toBe(
            app(SessionFingerprintInterface::class)->for(
                (string) request()->ip(),
                request()->userAgent(),
            ),
        );
    });

    it('skips enforcement when the binding is disabled', function (): void {
        config(['session.binding.enabled' => false]);

        $admin = $this->createAdmin();

        $this->loginAsAdmin($admin)->get('/admin/profile')->assertOk();

        $this->withHeader('User-Agent', 'Attacker Browser')
            ->get('/admin/profile')
            ->assertOk();
    });
});

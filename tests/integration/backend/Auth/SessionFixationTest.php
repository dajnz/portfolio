<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Auth;

use Tests\Support\Traits\InteractsWithAdmin;

uses(InteractsWithAdmin::class);

describe('session fixation protection', function (): void {
    it('regenerates the session id on password login', function (): void {
        $this->createAdmin();

        $this->get('/admin/login')->assertOk();
        $guestSessionId = session()->getId();

        $this->post('/admin/login', [
            'login' => 'admin',
            'password' => self::ADMIN_PASSWORD,
        ])->assertRedirect('/admin');

        expect(session()->getId())->not->toBe($guestSessionId);
    });

    it('regenerates the session id when entering the TOTP half-auth state', function (): void {
        $admin = $this->adminWithTotp();

        $this->get('/admin/login')->assertOk();
        $guestSessionId = session()->getId();

        $this->post('/admin/login', [
            'login' => $admin->login,
            'password' => self::ADMIN_PASSWORD,
        ])->assertRedirect(route('login.totp'));

        expect(session()->getId())->not->toBe($guestSessionId)
            ->and(session('totp.pending_admin_id'))->toBe((int) $admin->id);
    });

    it('regenerates the session id when the TOTP login completes', function (): void {
        $admin = $this->adminWithTotp();

        $this->pendingTotpSession($admin)->get('/admin/login/totp')->assertOk();
        $halfAuthSessionId = session()->getId();

        $this->post('/admin/login/totp', ['code' => $this->totpCode()])
            ->assertRedirect('/admin');

        expect(session()->getId())->not->toBe($halfAuthSessionId);
    });
});

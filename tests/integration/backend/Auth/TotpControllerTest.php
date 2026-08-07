<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Auth;

use App\Features\Auth\Domain\Entities\AdminTimedOtp;
use App\Features\Auth\Infra\Models\AdminModel;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Traits\InteractsWithAdmin;

uses(InteractsWithAdmin::class);

describe('TotpController login flow', function (): void {
    it('renders the TOTP verification page while in the half-auth state', function (): void {
        $admin = $this->adminWithTotp();

        $this->pendingTotpSession($admin)
            ->get('/admin/login/totp')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Admin/Pages/TotpVerificationPage')
                ->where('lock_remaining_seconds', null));
    });

    it('completes login with a valid TOTP code and resets failure counters', function (): void {
        $admin = AdminModel::factory()->withTotp(self::TOTP_SECRET)->create([
            'totp_failed_attempts' => 5,
            'totp_locked_until' => now()->subMinute(),
        ]);

        $this->pendingTotpSession($admin)
            ->post('/admin/login/totp', ['code' => $this->totpCode()])
            ->assertRedirect('/admin');

        $this->assertAuthenticated();
        expect(session('session_version'))->toBe((int) $admin->session_version)
            ->and(session('totp.pending_admin_id'))->toBeNull();

        $fresh = $admin->fresh();
        expect($fresh->totp_failed_attempts)->toBe(0)
            ->and($fresh->totp_locked_until)->toBeNull();
    });

    it('rejects an invalid code and increments the failed-attempt counter', function (): void {
        $admin = $this->adminWithTotp();

        $this->pendingTotpSession($admin)
            ->post('/admin/login/totp', ['code' => '000000'])
            ->assertRedirect()
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        expect($admin->fresh()->totp_failed_attempts)->toBe(1);
    });

    it('accepts an unused recovery code and marks it consumed', function (): void {
        $admin = $this->adminWithTotp(['AAAA-AAAA-AAAA']);

        $this->pendingTotpSession($admin)
            ->post('/admin/login/totp', ['code' => 'aaaa-aaaa-aaaa'])
            ->assertRedirect('/admin');

        $this->assertAuthenticated();

        $codes = $admin->fresh()->totp_recovery_codes;
        expect($codes[0]['used'])->toBeTrue();
    });

    it('rejects a consumed recovery code and counts it as a failed attempt', function (): void {
        $admin = $this->adminWithTotp(['AAAA-AAAA-AAAA']);

        $this->pendingTotpSession($admin)
            ->post('/admin/login/totp', ['code' => 'AAAA-AAAA-AAAA'])
            ->assertRedirect('/admin');

        $this->post('/admin/logout');

        $this->pendingTotpSession($admin->fresh())
            ->post('/admin/login/totp', ['code' => 'AAAA-AAAA-AAAA'])
            ->assertRedirect()
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        expect($admin->fresh()->totp_failed_attempts)->toBe(1);
    });

    it('applies the escalating lockout tiers (4-6 → 1 minute)', function (): void {
        $this->freezeTime();

        $admin = $this->adminWithTotp();

        foreach (range(1, 3) as $_) {
            $this->pendingTotpSession($admin->fresh())
                ->post('/admin/login/totp', ['code' => '000000']);
            expect($admin->fresh()->totp_locked_until)->toBeNull();
        }

        $this->pendingTotpSession($admin->fresh())
            ->post('/admin/login/totp', ['code' => '000000']);

        $fresh = $admin->fresh();
        expect($fresh->totp_failed_attempts)->toBe(4)
            ->and($fresh->totp_locked_until)->not->toBeNull()
            ->and($fresh->totp_locked_until->getTimestamp())->toBeBetween(
                now()->addSeconds(59)->getTimestamp(),
                now()->addSeconds(61)->getTimestamp(),
            );
    });

    it('escalates to a permanent lock on the 15th failed attempt', function (): void {
        $admin = $this->createAdmin([
            'totp_enabled' => true,
            'totp_secret' => \Illuminate\Support\Facades\Crypt::encryptString(self::TOTP_SECRET),
            'totp_failed_attempts' => 14,
        ]);

        $this->pendingTotpSession($admin)
            ->post('/admin/login/totp', ['code' => '000000'])
            ->assertRedirect();

        $lockedUntil = $admin->fresh()->totp_locked_until;
        expect(AdminTimedOtp::isPermanentLock($lockedUntil->toDateTimeImmutable(), new \DateTimeImmutable()))->toBeTrue();
    });

    it('rejects verification while locked and clears the half-auth state', function (): void {
        $admin = $this->adminWithTotp();
        $admin->update(['totp_failed_attempts' => 7, 'totp_locked_until' => now()->addMinutes(15)]);

        $this->pendingTotpSession($admin)
            ->post('/admin/login/totp', ['code' => '000000'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('notification', ['type' => 'error', 'message' => 'Too many attempts — try again in 15 minutes.']);

        $this->assertGuest();
        expect(session('totp.pending_admin_id'))->toBeNull();
    });

    it('rate-limits TOTP verification to 5 per minute per IP', function (): void {
        $admin = $this->adminWithTotp();

        for ($i = 0; $i < 5; $i++) {
            $this->pendingTotpSession($admin)
                ->post('/admin/login/totp', ['code' => '000000']);
        }

        $this->pendingTotpSession($admin)
            ->post('/admin/login/totp', ['code' => '000000'])
            ->assertStatus(429);
    });

    it('redirects to login without a pending half-auth state', function (): void {
        $this->get('/admin/login/totp')->assertRedirect(route('login'));
    });

    it('clears the half-auth state and flashes when expired', function (): void {
        $admin = $this->adminWithTotp();

        $this->withSession([
            'totp.pending_admin_id' => $admin->id,
            'totp.pending_until' => now()->subMinute()->getTimestamp(),
        ])->get('/admin/login/totp')
            ->assertRedirect(route('login'))
            ->assertSessionHas('notification', ['type' => 'error', 'message' => 'TOTP verification expired.']);

        expect(session('totp.pending_admin_id'))->toBeNull();
    });

    it('clears the half-auth state when the pending admin no longer exists', function (): void {
        $this->withSession([
            'totp.pending_admin_id' => 999,
            'totp.pending_until' => now()->addMinutes(5)->getTimestamp(),
        ])->get('/admin/login/totp')
            ->assertRedirect(route('login'));

        expect(session('totp.pending_admin_id'))->toBeNull();
    });

    it('bounces a locked admin to the login page with the lock message', function (): void {
        $admin = $this->adminWithTotp();
        $admin->update(['totp_locked_until' => now()->addMinutes(5)]);

        $this->pendingTotpSession($admin)
            ->get('/admin/login/totp')
            ->assertRedirect(route('login'))
            ->assertSessionHas('notification', ['type' => 'error', 'message' => 'Too many attempts — try again in 5 minutes.']);
    });

    it('bounces a permanently locked admin with the reset-password message', function (): void {
        $admin = $this->adminWithTotp();
        $admin->update(['totp_locked_until' => now()->addYears(AdminTimedOtp::PERMANENT_LOCK_YEARS)]);

        $this->pendingTotpSession($admin)
            ->get('/admin/login/totp')
            ->assertRedirect(route('login'))
            ->assertSessionHas('notification', ['type' => 'error', 'message' => 'Account locked — use `admin:reset-password` to regain access.']);
    });

    it('requires a code in TOTP verification', function (): void {
        $admin = $this->adminWithTotp();

        $this->pendingTotpSession($admin)
            ->post('/admin/login/totp', [])
            ->assertRedirect()
            ->assertSessionHasErrors('code');
    });
});

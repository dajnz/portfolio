<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Auth;

use App\Features\Auth\Infra\Models\AdminModel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Traits\InteractsWithAdmin;

uses(InteractsWithAdmin::class);

describe('ProfileController', function (): void {
    it('renders the profile page with its props', function (): void {
        $admin = $this->createAdmin(['session_version' => 2]);
        $this->enableGithubOauthFlag();

        $this->loginAsAdmin($admin)
            ->get('/admin/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Admin/Pages/ProfilePage')
                ->where('admin.login', $admin->login)
                ->where('admin.email', $admin->email)
                ->where('admin.github_linked', false)
                ->where('admin.totp_enabled', false)
                ->where('admin.session_version', 2)
                ->where('github_oauth_enabled', true));
    });

    it('reports github_linked and totp_enabled in the profile props', function (): void {
        $admin = AdminModel::factory()->withTotp(self::TOTP_SECRET)->create(['github_id' => '12345']);

        $this->loginAsAdmin($admin)
            ->get('/admin/profile')
            ->assertInertia(fn (Assert $page) => $page
                ->where('admin.github_linked', true)
                ->where('admin.totp_enabled', true));
    });

    it('updates the profile fields', function (): void {
        $admin = $this->createAdmin();

        $this->loginAsAdmin($admin)
            ->put('/admin/profile', [
                'name' => 'New Name',
                'email' => 'new@example.com',
                'login' => 'newlogin',
            ])->assertRedirect()
            ->assertSessionHas('notification', ['type' => 'success', 'message' => 'Profile updated successfully.']);

        $fresh = $admin->fresh();
        expect($fresh->name)->toBe('New Name')
            ->and($fresh->email)->toBe('new@example.com')
            ->and($fresh->login)->toBe('newlogin')
            ->and($fresh->session_version)->toBe(0);
    });

    it('changes the password with a valid current password and invalidates other sessions', function (): void {
        $admin = $this->createAdmin();

        $this->loginAsAdmin($admin)
            ->put('/admin/profile', [
                'name' => $admin->name,
                'email' => $admin->email,
                'login' => $admin->login,
                'current_password' => self::ADMIN_PASSWORD,
                'password' => 'NewAdminPassword456!',
                'password_confirmation' => 'NewAdminPassword456!',
            ])->assertRedirect();

        $fresh = $admin->fresh();
        expect(Hash::check('NewAdminPassword456!', $fresh->password))->toBeTrue()
            ->and($fresh->session_version)->toBe(1)
            ->and(session('session_version'))->toBe(1);

        // The current session stays valid under the new version.
        $this->get('/admin/profile')->assertOk();
    });

    it('rejects a wrong current password', function (): void {
        $admin = $this->createAdmin();

        $this->loginAsAdmin($admin)
            ->put('/admin/profile', [
                'name' => $admin->name,
                'email' => $admin->email,
                'login' => $admin->login,
                'current_password' => 'wrong-password',
                'password' => 'NewAdminPassword456!',
                'password_confirmation' => 'NewAdminPassword456!',
            ])->assertRedirect()
            ->assertSessionHasErrors(['current_password' => 'The current password is incorrect.']);

        expect(Hash::check(self::ADMIN_PASSWORD, $admin->fresh()->password))->toBeTrue();
    });

    it('rejects a weak new password', function (): void {
        $admin = $this->createAdmin();

        $this->loginAsAdmin($admin)
            ->put('/admin/profile', [
                'name' => $admin->name,
                'email' => $admin->email,
                'login' => $admin->login,
                'current_password' => self::ADMIN_PASSWORD,
                'password' => 'short',
                'password_confirmation' => 'short',
            ])->assertRedirect()
            ->assertSessionHasErrors('password');
    });

    it('rejects a 12-char password without mixed case and digits', function (): void {
        $admin = $this->createAdmin();

        $this->loginAsAdmin($admin)
            ->put('/admin/profile', [
                'name' => $admin->name,
                'email' => $admin->email,
                'login' => $admin->login,
                'current_password' => self::ADMIN_PASSWORD,
                'password' => '111111111111',
                'password_confirmation' => '111111111111',
            ])->assertRedirect()
            ->assertSessionHasErrors('password');

        expect(Hash::check(self::ADMIN_PASSWORD, $admin->fresh()->password))->toBeTrue();
    });

    it('rejects a password without a digit', function (): void {
        $admin = $this->createAdmin();

        $this->loginAsAdmin($admin)
            ->put('/admin/profile', [
                'name' => $admin->name,
                'email' => $admin->email,
                'login' => $admin->login,
                'current_password' => self::ADMIN_PASSWORD,
                'password' => 'MixedCaseWithoutDigits',
                'password_confirmation' => 'MixedCaseWithoutDigits',
            ])->assertRedirect()
            ->assertSessionHasErrors('password');
    });

    it('rejects an empty password-change intent instead of silently succeeding', function (): void {
        $admin = $this->createAdmin();

        $this->loginAsAdmin($admin)
            ->put('/admin/profile', [
                'name' => $admin->name,
                'email' => $admin->email,
                'login' => $admin->login,
                'current_password' => '',
                'password' => '',
                'password_confirmation' => '',
            ])->assertRedirect()
            ->assertSessionHasErrors(['current_password', 'password']);

        expect(Hash::check(self::ADMIN_PASSWORD, $admin->fresh()->password))->toBeTrue();
    });

    it('rejects a duplicate email or login', function (): void {
        $admin = $this->createAdmin();
        $this->createAdmin(['login' => 'other', 'email' => 'other@example.com']);

        $this->loginAsAdmin($admin)
            ->put('/admin/profile', [
                'name' => $admin->name,
                'email' => 'other@example.com',
                'login' => 'other',
            ])->assertRedirect()
            ->assertSessionHasErrors(['email', 'login']);
    });

    it('forces logout on other sessions when the version is bumped elsewhere', function (): void {
        $admin = $this->createAdmin();
        $this->loginAsAdmin($admin);

        // Simulates a password change / TOTP toggle from another device.
        $admin->increment('session_version');

        $this->get('/admin/profile')
            ->assertRedirect(route('login'))
            ->assertSessionHas('notification', ['type' => 'error', 'message' => 'Session expired — please log in again.']);

        $this->assertGuest();
    });

    describe('TOTP enrollment', function (): void {
        it('starts enrollment without a code and returns the QR + manual key', function (): void {
            $admin = $this->createAdmin();

            $response = $this->loginAsAdmin($admin)
                ->postJson('/admin/profile/totp-enable', [])
                ->assertOk()
                ->assertJsonStructure(['data' => ['qr_code_svg', 'manual_key']]);

            $manualKey = $response->json('data.manual_key');
            expect($manualKey)->toMatch('/^[A-Z2-7]{16}$/')
                ->and($response->json('data.qr_code_svg'))->toContain('<svg')
                ->and(session('totp_enroll.pending_secret'))->toBe($manualKey);
        });

        it('verifies the code and enables TOTP, returning 10 recovery codes', function (): void {
            $admin = $this->createAdmin();

            $this->loginAsAdmin($admin)
                ->postJson('/admin/profile/totp-enable', [])
                ->assertOk();

            $secret = session('totp_enroll.pending_secret');

            $this->postJson('/admin/profile/totp-enable', ['code' => app(\App\Features\Auth\Infra\Services\TotpService::class)->currentOtp($secret)])
                ->assertOk()
                ->assertJsonCount(10, 'data.recovery_codes');

            $fresh = $admin->fresh();
            expect($fresh->totp_enabled)->toBeTrue()
                ->and(Crypt::decryptString($fresh->totp_secret))->toBe($secret)
                ->and($fresh->totp_recovery_codes)->toHaveCount(10)
                ->and($fresh->session_version)->toBe(1)
                ->and(session('session_version'))->toBe(1)
                ->and(session('totp_enroll.pending_secret'))->toBeNull();
        });

        it('rejects a wrong code during enrollment', function (): void {
            $admin = $this->createAdmin();
            $this->loginAsAdmin($admin)->postJson('/admin/profile/totp-enable', [])->assertOk();

            $this->postJson('/admin/profile/totp-enable', ['code' => '000000'])
                ->assertStatus(422)
                ->assertJsonValidationErrors('code');

            expect($admin->fresh()->totp_enabled)->toBeFalse();
        });

        it('rejects verification after the pending secret expired', function (): void {
            $admin = $this->createAdmin();
            $this->loginAsAdmin($admin)->postJson('/admin/profile/totp-enable', [])->assertOk();
            session()->forget('totp_enroll.pending_secret');

            $this->postJson('/admin/profile/totp-enable', ['code' => '123456'])
                ->assertStatus(422)
                ->assertJsonValidationErrors('code');
        });
    });

    describe('TOTP disable + recovery codes', function (): void {
        it('disables TOTP with the current password and bumps the session version', function (): void {
            $admin = $this->adminWithTotp(['AAAA-AAAA-AAAA']);

            $this->loginAsAdmin($admin)
                ->post('/admin/profile/totp-disable', ['current_password' => self::ADMIN_PASSWORD])
                ->assertRedirect()
                ->assertSessionHas('notification', ['type' => 'success', 'message' => 'TOTP disabled.']);

            $fresh = $admin->fresh();
            expect($fresh->totp_enabled)->toBeFalse()
                ->and($fresh->totp_secret)->toBeNull()
                ->and($fresh->totp_recovery_codes)->toBeNull()
                ->and($fresh->session_version)->toBe(1)
                ->and(session('session_version'))->toBe(1);

            $this->get('/admin/profile')->assertOk();
        });

        it('rejects disabling TOTP with a wrong password', function (): void {
            $admin = $this->adminWithTotp();

            $this->loginAsAdmin($admin)
                ->post('/admin/profile/totp-disable', ['current_password' => 'wrong'])
                ->assertRedirect()
                ->assertSessionHasErrors(['current_password' => 'The current password is incorrect.']);

            expect($admin->fresh()->totp_enabled)->toBeTrue();
        });

        it('regenerates recovery codes and invalidates the old ones', function (): void {
            $admin = $this->adminWithTotp(['AAAA-AAAA-AAAA']);
            $this->loginAsAdmin($admin);

            $response = $this->postJson('/admin/profile/totp-recovery-codes', ['current_password' => self::ADMIN_PASSWORD])
                ->assertOk()
                ->assertJsonCount(10, 'data.recovery_codes');

            $codes = $admin->fresh()->totp_recovery_codes;
            expect($codes)->toHaveCount(10)
                ->and($response->json('data.recovery_codes'))->not->toContain('AAAA-AAAA-AAAA')
                ->and($admin->fresh()->session_version)->toBe(0);
        });

        it('rejects regenerating recovery codes with a wrong password', function (): void {
            $admin = $this->adminWithTotp(['AAAA-AAAA-AAAA']);

            $this->loginAsAdmin($admin)
                ->postJson('/admin/profile/totp-recovery-codes', ['current_password' => 'wrong'])
                ->assertStatus(422)
                ->assertJsonValidationErrors('current_password');
        });

        it('requires the current password for disable and regeneration', function (): void {
            $admin = $this->adminWithTotp();

            $this->loginAsAdmin($admin)
                ->post('/admin/profile/totp-disable', [])
                ->assertRedirect()
                ->assertSessionHasErrors('current_password');

            $this->postJson('/admin/profile/totp-recovery-codes', [])
                ->assertStatus(422)
                ->assertJsonValidationErrors('current_password');
        });
    });

    it('requires authentication for profile routes', function (): void {
        $this->get('/admin/profile')->assertRedirect(route('login'));
        $this->post('/admin/profile/github-unlink')->assertRedirect(route('login'));
    });
});

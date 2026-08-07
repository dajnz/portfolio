<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Auth;

use Illuminate\Support\Facades\Crypt;
use Tests\Support\Traits\InteractsWithAdmin;

uses(InteractsWithAdmin::class);

describe('TotpController github-link flow', function (): void {
    beforeEach(function (): void {
        $this->enableGithubOauthFlag();
    });
    it('renders the TOTP confirmation page with pending link data', function (): void {
        $admin = $this->adminWithTotp();

        $this->loginAsAdmin($admin)
            ->withSession([
                'github_link.pending_admin_id' => $admin->id,
                'github_link.pending_github_id' => '12345',
                'github_link.pending_token' => 'tok',
                'github_link.pending_until' => now()->addMinutes(5)->getTimestamp(),
            ])
            ->get('/admin/profile/github-link/totp')
            ->assertOk();
    });

    it('completes the link after a valid TOTP code and bumps the session version', function (): void {
        $admin = $this->adminWithTotp();

        $this->loginAsAdmin($admin)
            ->withSession([
                'github_link.pending_admin_id' => $admin->id,
                'github_link.pending_github_id' => '12345',
                'github_link.pending_token' => 'secret-token',
                'github_link.pending_until' => now()->addMinutes(5)->getTimestamp(),
            ])
            ->post('/admin/profile/github-link/totp', ['code' => $this->totpCode()])
            ->assertRedirect(route('profile'))
            ->assertSessionHas('notification', ['type' => 'success', 'message' => 'GitHub account linked successfully.']);

        $fresh = $admin->fresh();
        expect($fresh->github_id)->toBe('12345')
            ->and(Crypt::decryptString($fresh->github_token))->toBe('secret-token')
            ->and($fresh->session_version)->toBe((int) $admin->session_version + 1)
            ->and(session('session_version'))->toBe((int) $fresh->session_version);

        expect(session('github_link.pending_admin_id'))->toBeNull()
            ->and(session('github_link.pending_github_id'))->toBeNull();
    });

    it('rejects a wrong code and keeps the pending link data', function (): void {
        $admin = $this->adminWithTotp();

        $this->loginAsAdmin($admin)
            ->withSession([
                'github_link.pending_admin_id' => $admin->id,
                'github_link.pending_github_id' => '12345',
                'github_link.pending_token' => 'tok',
                'github_link.pending_until' => now()->addMinutes(5)->getTimestamp(),
            ])
            ->post('/admin/profile/github-link/totp', ['code' => '000000'])
            ->assertRedirect()
            ->assertSessionHasErrors('code');

        expect($admin->fresh()->github_id)->toBeNull()
            ->and($admin->fresh()->totp_failed_attempts)->toBe(1)
            ->and(session('github_link.pending_github_id'))->toBe('12345');
    });

    it('clears the pending link and redirects to the profile when locked', function (): void {
        $admin = $this->adminWithTotp();
        $admin->update(['totp_locked_until' => now()->addMinutes(5)]);

        $this->loginAsAdmin($admin)
            ->withSession([
                'github_link.pending_admin_id' => $admin->id,
                'github_link.pending_github_id' => '12345',
                'github_link.pending_token' => 'tok',
                'github_link.pending_until' => now()->addMinutes(5)->getTimestamp(),
            ])
            ->get('/admin/profile/github-link/totp')
            ->assertRedirect(route('profile'))
            ->assertSessionHas('notification', ['type' => 'error', 'message' => 'Too many attempts — try again in 5 minutes.']);

        expect(session('github_link.pending_admin_id'))->toBeNull();
    });

    it('redirects to the profile when the pending link data is missing', function (): void {
        $admin = $this->adminWithTotp();
        $this->loginAsAdmin($admin);

        $this->get('/admin/profile/github-link/totp')->assertRedirect(route('profile'));
    });

    it('redirects to login when not authenticated', function (): void {
        $this->get('/admin/profile/github-link/totp')->assertRedirect(route('login'));
    });

    it('redirects to the profile when the pending admin differs from the authenticated one', function (): void {
        $admin = $this->adminWithTotp();
        $other = $this->createAdmin(['login' => 'other', 'email' => 'other@example.com']);

        $this->loginAsAdmin($admin)
            ->withSession([
                'github_link.pending_admin_id' => $other->id,
                'github_link.pending_github_id' => '12345',
                'github_link.pending_token' => 'tok',
                'github_link.pending_until' => now()->addMinutes(5)->getTimestamp(),
            ])
            ->get('/admin/profile/github-link/totp')
            ->assertRedirect(route('profile'));

        expect(session('github_link.pending_admin_id'))->toBeNull();
    });
});

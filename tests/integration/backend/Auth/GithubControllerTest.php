<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Auth;

use App\Features\Auth\Infra\Models\AdminModel;
use Illuminate\Support\Facades\Crypt;
use Laravel\Socialite\Facades\Socialite;
use Tests\Support\Traits\InteractsWithAdmin;

uses(InteractsWithAdmin::class);

describe('GithubController', function (): void {
    beforeEach(function (): void {
        $this->enableGithubOauthFlag();
    });

    it('stores the login intent and redirects to GitHub', function (): void {
        Socialite::fake('github');

        $this->get('/admin/login/github')
            ->assertRedirect('https://socialite.fake/github/authorize');

        expect(session('oauth_intent'))->toBe('login');
    });

    it('stores the link intent and redirects to GitHub', function (): void {
        Socialite::fake('github');
        $this->loginAsAdmin($this->createAdmin());

        $this->get('/admin/profile/github-link')
            ->assertRedirect('https://socialite.fake/github/authorize');

        expect(session('oauth_intent'))->toBe('link');
    });

    it('authenticates via GitHub when the account exists and TOTP is disabled', function (): void {
        $this->createAdmin(['github_id' => '12345', 'github_token' => Crypt::encryptString('old-token')]);
        Socialite::fake('github', $this->githubUser('12345'));

        $this->withSession(['oauth_intent' => 'login'])
            ->get('/admin/login/github/callback')
            ->assertRedirect('/admin');

        $this->assertAuthenticated();
        expect(session('oauth_intent'))->toBeNull();
    });

    it('enters the half-auth state when the GitHub account requires TOTP', function (): void {
        $admin = AdminModel::factory()->withTotp(self::TOTP_SECRET)->create(['github_id' => '12345']);
        Socialite::fake('github', $this->githubUser('12345'));

        $this->withSession(['oauth_intent' => 'login'])
            ->get('/admin/login/github/callback')
            ->assertRedirect(route('login.totp'));

        $this->assertGuest();
        expect(session('totp.pending_admin_id'))->toBe((int) $admin->id);
    });

    it('flashes an error when no account is linked to the GitHub ID', function (): void {
        Socialite::fake('github', $this->githubUser('99999'));

        $this->withSession(['oauth_intent' => 'login'])
            ->get('/admin/login/github/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHas('notification', ['type' => 'error', 'message' => 'No admin account linked to this GitHub account.']);

        $this->assertGuest();
    });

    it('rejects the login intent with 400 when already authenticated', function (): void {
        $this->loginAsAdmin($this->createAdmin());
        Socialite::fake('github', $this->githubUser('12345'));

        $this->withSession(['oauth_intent' => 'login'])
            ->get('/admin/login/github/callback')
            ->assertStatus(400);
    });

    it('redirects to login for the link intent without an authenticated session', function (): void {
        Socialite::fake('github', $this->githubUser('12345'));

        $this->withSession(['oauth_intent' => 'link'])
            ->get('/admin/login/github/callback')
            ->assertRedirect(route('login'));
    });

    it('links the GitHub account without TOTP and bumps the session version', function (): void {
        $admin = $this->createAdmin();
        Socialite::fake('github', $this->githubUser('12345', 'new-token'));

        $this->loginAsAdmin($admin)
            ->withSession(['oauth_intent' => 'link'])
            ->get('/admin/login/github/callback')
            ->assertRedirect(route('profile'))
            ->assertSessionHas('notification', ['type' => 'success', 'message' => 'GitHub account linked successfully.']);

        $fresh = $admin->fresh();
        expect($fresh->github_id)->toBe('12345')
            ->and(Crypt::decryptString($fresh->github_token))->toBe('new-token')
            ->and($fresh->session_version)->toBe(1)
            ->and(session('session_version'))->toBe(1);
    });

    it('is a no-op when the GitHub ID is already linked to the same admin', function (): void {
        $admin = $this->createAdmin(['github_id' => '12345', 'github_token' => Crypt::encryptString('tok')]);
        Socialite::fake('github', $this->githubUser('12345'));

        $this->loginAsAdmin($admin)
            ->withSession(['oauth_intent' => 'link'])
            ->get('/admin/login/github/callback')
            ->assertRedirect(route('profile'))
            ->assertSessionHas('notification', ['type' => 'error', 'message' => 'GitHub account is already linked.']);

        expect($admin->fresh()->session_version)->toBe(0)
            ->and($admin->fresh()->github_token)->not->toBeNull();
    });

    it('rejects the link when the GitHub ID belongs to a different admin', function (): void {
        $admin = $this->createAdmin();
        $this->createAdmin(['login' => 'other', 'email' => 'other@example.com', 'github_id' => '12345']);
        Socialite::fake('github', $this->githubUser('12345'));

        $this->loginAsAdmin($admin)
            ->withSession(['oauth_intent' => 'link'])
            ->get('/admin/login/github/callback')
            ->assertRedirect(route('profile'))
            ->assertSessionHas('notification', ['type' => 'error', 'message' => 'This GitHub account is already linked to a different admin account.']);

        expect($admin->fresh()->github_id)->toBeNull();
    });

    it('requires TOTP re-authentication before linking when TOTP is enabled', function (): void {
        $admin = $this->adminWithTotp();
        Socialite::fake('github', $this->githubUser('12345', 'pending-token'));

        $this->loginAsAdmin($admin)
            ->withSession(['oauth_intent' => 'link'])
            ->get('/admin/login/github/callback')
            ->assertRedirect(route('profile.github-link.totp'));

        expect(session('github_link.pending_admin_id'))->toBe((int) $admin->id)
            ->and(session('github_link.pending_github_id'))->toBe('12345')
            ->and(session('github_link.pending_token'))->toBe('pending-token')
            ->and($admin->fresh()->github_id)->toBeNull();
    });

    it('returns an error for a missing OAuth intent', function (): void {
        Socialite::fake('github', $this->githubUser('12345'));

        $this->get('/admin/login/github/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHas('notification', ['type' => 'error', 'message' => 'Invalid OAuth intent.']);
    });

    it('unlinks the GitHub account and bumps the session version', function (): void {
        $admin = $this->createAdmin(['github_id' => '12345', 'github_token' => Crypt::encryptString('tok')]);

        $this->loginAsAdmin($admin)
            ->post('/admin/profile/github-unlink')
            ->assertRedirect()
            ->assertSessionHas('notification', ['type' => 'success', 'message' => 'GitHub account unlinked.']);

        $fresh = $admin->fresh();
        expect($fresh->github_id)->toBeNull()
            ->and($fresh->github_token)->toBeNull()
            ->and($fresh->session_version)->toBe(1)
            ->and(session('session_version'))->toBe(1);
    });
});

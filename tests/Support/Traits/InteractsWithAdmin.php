<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

use App\Features\Auth\Infra\Models\AdminModel;
use App\Features\Auth\Infra\Services\TotpService;

/**
 * Admin fixtures + half-auth session helpers for the Auth test suites.
 */
trait InteractsWithAdmin
{
    protected const ADMIN_PASSWORD = 'AdminPassword123!';

    protected const TOTP_SECRET = 'JBSWY3DPEHPK3PXP';

    protected function createAdmin(array $attributes = []): AdminModel
    {
        return AdminModel::factory()->create($attributes);
    }

    protected function adminWithTotp(array $recoveryCodes = []): AdminModel
    {
        return AdminModel::factory()->withTotp(self::TOTP_SECRET, $recoveryCodes)->create();
    }

    /** Current 6-digit TOTP code for the test secret (or an explicit one). */
    protected function totpCode(?string $secret = null): string
    {
        return app(TotpService::class)->currentOtp($secret ?? self::TOTP_SECRET);
    }

    /** Enters the login half-auth state for the admin. */
    protected function pendingTotpSession(AdminModel $admin): static
    {
        return $this->withSession([
            'totp.pending_admin_id' => $admin->id,
            'totp.pending_until' => now()->addMinutes(5)->getTimestamp(),
        ]);
    }

    /** Fully authenticates as the admin with the session version stored. */
    protected function loginAsAdmin(AdminModel $admin): static
    {
        return $this->actingAs($admin)
            ->withSession(['session_version' => (int) $admin->session_version]);
    }

    /** Enables the github_oauth feature flag (OAuth routes are gated by it). */
    protected function enableGithubOauthFlag(): static
    {
        \App\Features\Settings\Infra\Models\FeatureFlagModel::query()->updateOrCreate(
            ['key' => 'github_oauth'],
            ['enabled' => true, 'description' => ''],
        );

        return $this;
    }

    /** Builds a fake Socialite GitHub user. */
    protected function githubUser(string $id, string $token = 'github-token'): \Laravel\Socialite\Two\User
    {
        $user = new \Laravel\Socialite\Two\User();
        $user->map([
            'id' => $id,
            'nickname' => 'octo',
            'name' => 'Octo Cat',
            'email' => 'octo@example.com',
        ]);
        $user->token = $token;

        return $user;
    }
}

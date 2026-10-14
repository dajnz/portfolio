<?php

declare(strict_types=1);

namespace App\Features\Auth\App\UseCases;

use App\Features\Auth\App\DTOs\AdminProfileDto;
use App\Features\Auth\App\Services\AdminResolver;
use App\Features\Settings\Domain\Enums\FeatureFlagName;
use App\Features\Settings\Domain\RepositoryInterfaces\FeatureFlagRepositoryInterface;

/**
 * The admin profile page data: identity, GitHub link state, TOTP state
 * (enabled + remaining recovery codes) and the GitHub OAuth availability.
 */
final class GetAdminProfileUseCase
{
    public function __construct(
        private readonly AdminResolver $admins,
        private readonly FeatureFlagRepositoryInterface $flags,
    ) {
    }

    public function get(int $adminId): AdminProfileDto
    {
        $admin = $this->admins->require($adminId);

        return new AdminProfileDto(
            login: $admin->toLogin(),
            email: $admin->toEmail(),
            githubLinked: $admin->isGithubLinked(),
            totpEnabled: $admin->toTimedOtp()->isEnabled(),
            recoveryCodesRemaining: $admin->toTimedOtp()->toRecoveryCodesRemaining(),
            sessionVersion: $admin->toSessionVersion(),
            githubOauthEnabled: $this->flags->isEnabled(FeatureFlagName::GithubOauth),
        );
    }
}

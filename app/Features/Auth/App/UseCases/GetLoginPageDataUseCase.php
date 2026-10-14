<?php

declare(strict_types=1);

namespace App\Features\Auth\App\UseCases;

use App\Features\Auth\App\DTOs\LoginPageDataDto;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminRepositoryInterface;
use App\Features\Settings\Domain\Enums\FeatureFlagName;
use App\Features\Settings\Domain\RepositoryInterfaces\FeatureFlagRepositoryInterface;

/**
 * The login page data: the GitHub button shows only when the OAuth flag is
 * on AND at least one admin account is linked — the OAuth login flow can
 * only fail otherwise.
 */
final class GetLoginPageDataUseCase
{
    public function __construct(
        private readonly FeatureFlagRepositoryInterface $flags,
        private readonly AdminRepositoryInterface $admins,
    ) {
    }

    public function get(): LoginPageDataDto
    {
        return new LoginPageDataDto(
            githubLoginAvailable: $this->flags->isEnabled(FeatureFlagName::GithubOauth)
                && $this->admins->hasGithubLinkedAdmin(),
        );
    }
}

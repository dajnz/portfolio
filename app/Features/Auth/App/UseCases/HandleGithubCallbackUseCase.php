<?php

declare(strict_types=1);

namespace App\Features\Auth\App\UseCases;

use App\Features\Auth\Domain\Enums\GithubLoginOutcome;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminRepositoryInterface;
use App\Features\Shared\App\BaseUseCase;

/**
 * GitHub OAuth login intent (`oauth_intent = 'login'`): resolves the admin
 * by GitHub ID and either authenticates, enters the half-auth TOTP state,
 * or reports that no account is linked. GitHub OAuth never bypasses TOTP.
 */
final class HandleGithubCallbackUseCase extends BaseUseCase
{
    public function __construct(
        private readonly AdminRepositoryInterface $repository,
        private readonly LoginUseCase $login,
    ) {
    }

    public function handle(string $githubId, string $ip): GithubLoginOutcome
    {
        $admin = $this->repository->findByGithubId($githubId);

        if ($admin === null) {
            return GithubLoginOutcome::NoAccount;
        }

        if ($admin->toTimedOtp()->isEnabled()) {
            $this->login->beginTotpVerification($admin);

            return GithubLoginOutcome::TotpRequired;
        }

        $this->login->completeLogin($admin, $ip);

        return GithubLoginOutcome::Authenticated;
    }
}

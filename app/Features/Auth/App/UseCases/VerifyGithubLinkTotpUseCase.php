<?php

declare(strict_types=1);

namespace App\Features\Auth\App\UseCases;

use App\Features\Auth\App\Contracts\TotpLockoutMessengerInterface;
use App\Features\Auth\App\Services\GithubLinkService;
use App\Features\Auth\App\Services\TotpVerifier;
use App\Features\Auth\Domain\Exceptions\GithubAlreadyLinkedException;
use App\Features\Auth\Domain\Exceptions\GithubConflictException;
use App\Features\Auth\Domain\Exceptions\GithubLinkPendingInvalidException;
use App\Features\Auth\Domain\Exceptions\TotpLockedException;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminRepositoryInterface;
use App\Features\Shared\App\BaseUseCase;
use App\Features\Shared\App\Contracts\AdminSessionInterface;

/**
 * GitHub-link TOTP re-authentication: resolves the pending link data from
 * the session, verifies the code (backoff by `TotpVerifier`) and completes
 * the link. A lockout or invalid pending state clears the `github_link.*`
 * keys first.
 */
final class VerifyGithubLinkTotpUseCase extends BaseUseCase
{
    public function __construct(
        private readonly AdminRepositoryInterface $repository,
        private readonly TotpVerifier $totpVerifier,
        private readonly GithubLinkService $links,
        private readonly TotpLockoutMessengerInterface $lockoutMessages,
        private readonly AdminSessionInterface $sessions,
    ) {
    }

    /**
     * @throws GithubLinkPendingInvalidException when the pending state is invalid (cleared)
     * @throws TotpLockedException               when a lockout is active (pending state cleared)
     * @throws \App\Features\Auth\Domain\Exceptions\InvalidTotpCodeException
     * @throws GithubAlreadyLinkedException
     * @throws GithubConflictException
     */
    public function verify(string $code, string $ip): void
    {
        $admin = $this->repository->findById((int) $this->sessions->get('github_link.pending_admin_id'));

        if ($admin === null || ! $this->sessions->check() || $this->sessions->id() !== $admin->toId()) {
            $this->clearPending();

            throw new GithubLinkPendingInvalidException();
        }

        try {
            $this->totpVerifier->verify($admin, $code, $ip);
        } catch (TotpLockedException $exception) {
            $this->clearPending();

            throw new TotpLockedException($this->lockoutMessages->totpLockoutFlash($admin));
        }

        $githubId = (string) $this->sessions->pull('github_link.pending_github_id');
        $githubToken = (string) $this->sessions->pull('github_link.pending_token');
        $this->clearPending();

        $this->links->link($admin->toId(), $githubId, $githubToken);
    }

    private function clearPending(): void
    {
        $this->sessions->forget([
            'github_link.pending_admin_id',
            'github_link.pending_github_id',
            'github_link.pending_token',
            'github_link.pending_until',
        ]);
    }
}

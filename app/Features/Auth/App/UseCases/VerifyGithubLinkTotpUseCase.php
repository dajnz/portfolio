<?php

declare(strict_types=1);

namespace App\Features\Auth\App\UseCases;

use App\Features\Auth\Domain\Exceptions\GithubAlreadyLinkedException;
use App\Features\Auth\Domain\Exceptions\GithubConflictException;
use App\Features\Auth\Domain\Exceptions\GithubLinkPendingInvalidException;
use App\Features\Auth\Domain\Exceptions\TotpLockedException;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminRepositoryInterface;
use App\Features\Auth\Infra\Services\AdminAuthService;
use App\Features\Shared\App\BaseUseCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * GitHub-link TOTP re-authentication: resolves the pending link data from
 * the session, verifies the code (backoff by `VerifyTotpUseCase`) and
 * completes the link. A lockout or invalid pending state clears the
 * `github_link.*` keys first.
 */
final class VerifyGithubLinkTotpUseCase extends BaseUseCase
{
    public function __construct(
        private readonly AdminRepositoryInterface $repository,
        private readonly VerifyTotpUseCase $verifyTotp,
        private readonly LinkGithubUseCase $linkGithub,
        private readonly AdminAuthService $auth,
    ) {
    }

    /**
     * @throws GithubLinkPendingInvalidException when the pending state is invalid (cleared)
     * @throws TotpLockedException               when a lockout is active (pending state cleared)
     * @throws \App\Features\Auth\Domain\Exceptions\InvalidTotpCodeException
     * @throws GithubAlreadyLinkedException
     * @throws GithubConflictException
     */
    public function verify(string $code): void
    {
        $admin = $this->repository->findById((int) Session::get('github_link.pending_admin_id'));

        if ($admin === null || ! Auth::check() || (int) Auth::id() !== $admin->toId()) {
            $this->clearPending();

            throw new GithubLinkPendingInvalidException();
        }

        try {
            $this->verifyTotp->verify($admin, $code);
        } catch (TotpLockedException $exception) {
            $this->clearPending();

            throw new TotpLockedException($this->auth->totpLockoutFlash($admin));
        }

        $githubId = (string) Session::pull('github_link.pending_github_id');
        $githubToken = (string) Session::pull('github_link.pending_token');
        $this->clearPending();

        $this->linkGithub->link($admin, $githubId, $githubToken);
    }

    private function clearPending(): void
    {
        Session::forget([
            'github_link.pending_admin_id',
            'github_link.pending_github_id',
            'github_link.pending_token',
            'github_link.pending_until',
        ]);
    }
}

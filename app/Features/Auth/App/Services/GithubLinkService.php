<?php

declare(strict_types=1);

namespace App\Features\Auth\App\Services;

use App\Features\Auth\Domain\Entities\Admin;
use App\Features\Auth\Domain\Enums\GithubLinkOutcome;
use App\Features\Auth\Domain\Exceptions\GithubAlreadyLinkedException;
use App\Features\Auth\Domain\Exceptions\GithubConflictException;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminRepositoryInterface;
use App\Features\Shared\App\Contracts\AdminSessionInterface;
use DateTimeImmutable;

/**
 * GitHub link/unlink mechanics: the conflict matrix, the TOTP-gated pending
 * state and the persisted link. A successful link/unlink bumps the session
 * version (all other sessions are force-logged-out; the current one is
 * re-synced).
 *
 * Shared by the link intent flow and the TOTP re-authentication flow.
 */
final class GithubLinkService
{
    private const PENDING_TTL_SECONDS = 300;

    public function __construct(
        private readonly AdminRepositoryInterface $repository,
        private readonly AdminResolver $admins,
        private readonly SessionVersionSynchronizer $sessionVersion,
        private readonly AdminSessionInterface $sessions,
    ) {
    }

    /**
     * Prepares the account linking: runs the conflict matrix and either
     * stores the TOTP-gated pending state or links immediately.
     */
    public function prepareAccountLinking(int $adminId, string $githubId, string $githubToken): GithubLinkOutcome
    {
        $admin = $this->admins->require($adminId);

        if ($admin->hasGithubId($githubId)) {
            return GithubLinkOutcome::AlreadyLinked;
        }

        if ($this->repository->githubIdTakenByOther($githubId, $admin->toId())) {
            return GithubLinkOutcome::Conflict;
        }

        if ($admin->toTimedOtp()->isEnabled()) {
            $this->preparePendingLink($admin, $githubId, $githubToken);

            return GithubLinkOutcome::TotpRequired;
        }

        $this->link($adminId, $githubId, $githubToken);

        return GithubLinkOutcome::Linked;
    }

    /**
     * Persists the GitHub link after the conflict checks.
     *
     * @throws GithubAlreadyLinkedException when the ID is already bound to this admin
     * @throws GithubConflictException      when the ID is bound to a different admin
     */
    public function link(int $adminId, string $githubId, string $githubToken): void
    {
        $admin = $this->admins->require($adminId);

        if ($admin->hasGithubId($githubId)) {
            throw new GithubAlreadyLinkedException();
        }

        if ($this->repository->githubIdTakenByOther($githubId, $admin->toId())) {
            throw new GithubConflictException();
        }

        $this->repository->save($admin->withGithubLink($githubId, $githubToken));
        $this->sessionVersion->bumpAndSync($admin->toId());
    }

    public function unlink(int $adminId): void
    {
        $admin = $this->admins->require($adminId);

        $this->repository->save($admin->withoutGithubLink());
        $this->sessionVersion->bumpAndSync($admin->toId());
    }

    /** Stores the pending GitHub data with a 5-minute expiry. */
    private function preparePendingLink(Admin $admin, string $githubId, string $githubToken): void
    {
        $this->sessions->put([
            'github_link.pending_admin_id' => $admin->toId(),
            'github_link.pending_github_id' => $githubId,
            'github_link.pending_token' => $githubToken,
            'github_link.pending_until' => (new DateTimeImmutable())->modify('+'.self::PENDING_TTL_SECONDS.' seconds')->getTimestamp(),
        ]);
    }
}

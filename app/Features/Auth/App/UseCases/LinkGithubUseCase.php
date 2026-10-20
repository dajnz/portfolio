<?php

declare(strict_types=1);

namespace App\Features\Auth\App\UseCases;

use App\Features\Auth\App\Services\GithubLinkService;
use App\Features\Auth\Domain\Enums\GithubLinkOutcome;
use App\Features\Shared\App\BaseUseCase;

/**
 * GitHub link/unlink entry point: the link intent flow — conflict matrix,
 * TOTP-gated pending state, completion — and the unlink action are delegated
 * to `GithubLinkService`.
 */
final class LinkGithubUseCase extends BaseUseCase
{
    public function __construct(
        private readonly GithubLinkService $links,
    ) {
    }

    /** Runs the link intent flow and reports its outcome (no exceptions). */
    public function linkOrPrepare(int $adminId, string $githubId, string $githubToken): GithubLinkOutcome
    {
        return $this->links->prepareAccountLinking($adminId, $githubId, $githubToken);
    }

    public function unlink(int $adminId): void
    {
        $this->links->unlink($adminId);
    }
}

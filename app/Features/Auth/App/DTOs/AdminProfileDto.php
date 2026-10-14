<?php

declare(strict_types=1);

namespace App\Features\Auth\App\DTOs;

/**
 * The admin profile page data. Immutable.
 */
final readonly class AdminProfileDto
{
    public function __construct(
        public string $login,
        public string $email,
        public bool $githubLinked,
        public bool $totpEnabled,
        public int $recoveryCodesRemaining,
        public int $sessionVersion,
        public bool $githubOauthEnabled,
    ) {
    }
}

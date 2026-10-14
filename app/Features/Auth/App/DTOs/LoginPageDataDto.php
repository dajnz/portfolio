<?php

declare(strict_types=1);

namespace App\Features\Auth\App\DTOs;

/**
 * The login page data. The GitHub button renders only when the OAuth flag
 * is on and at least one admin account is linked. Immutable.
 */
final readonly class LoginPageDataDto
{
    public function __construct(
        public bool $githubLoginAvailable,
    ) {
    }
}

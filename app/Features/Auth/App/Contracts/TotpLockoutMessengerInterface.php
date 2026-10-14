<?php

declare(strict_types=1);

namespace App\Features\Auth\App\Contracts;

use App\Features\Auth\Domain\Entities\Admin;

/**
 * Resolves the localized TOTP lockout message for a locked admin. An
 * abstraction over the translation stack so the app layer never names it;
 * the framework-backed implementation lives on the infra layer.
 */
interface TotpLockoutMessengerInterface
{
    public function totpLockoutFlash(Admin $admin): string;
}

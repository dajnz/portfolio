<?php

declare(strict_types=1);

namespace App\Features\Auth\Domain\RepositoryInterfaces;

use App\Features\Auth\Domain\Entities\AdminTimedOtp;

/**
 * Persistence for the admin's TOTP posture (`totp_*` columns on the same
 * `admins` row — separate from `AdminRepositoryInterface` so the aggregate
 * part owns its own storage path).
 */
interface AdminTimedOtpRepositoryInterface
{
    public function saveForAdmin(int $adminId, AdminTimedOtp $otp): void;

    /** Atomic increment of `totp_failed_attempts`; returns the new count. */
    public function incrementFailedAttempts(int $adminId): int;
}

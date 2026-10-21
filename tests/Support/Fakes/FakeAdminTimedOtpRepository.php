<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Features\Auth\Domain\Entities\AdminTimedOtp;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminTimedOtpRepositoryInterface;

final class FakeAdminTimedOtpRepository implements AdminTimedOtpRepositoryInterface
{
    /** @var list<array{adminId: int, otp: AdminTimedOtp}> */
    public array $saved = [];

    public function saveForAdmin(int $adminId, AdminTimedOtp $otp): void
    {
        $this->saved[] = ['adminId' => $adminId, 'otp' => $otp];
    }

    public function incrementFailedAttempts(int $adminId): int
    {
        return 1;
    }
}

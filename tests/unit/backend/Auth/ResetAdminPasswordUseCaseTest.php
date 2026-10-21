<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Auth;

use App\Features\Auth\App\UseCases\ResetAdminPasswordUseCase;
use App\Features\Auth\Domain\Entities\Admin;
use App\Features\Auth\Domain\Entities\AdminTimedOtp;
use App\Features\Auth\Domain\Exceptions\AdminNotFoundException;
use Tests\Support\Fakes\FakeAdminRepository;
use Tests\Support\Fakes\FakeAdminTimedOtpRepository;

function resetTargetAdmin(): Admin
{
    return new Admin(
        id: 1,
        name: 'admin',
        email: 'admin@example.com',
        login: 'admin',
        passwordHash: 'old-hash',
        githubId: null,
        githubToken: null,
        sessionVersion: 4,
        timedOtp: AdminTimedOtp::disabled(),
    );
}

describe('ResetAdminPasswordUseCase', function (): void {
    it('changes the password, resets the TOTP lockout and bumps the session version', function (): void {
        $admins = new FakeAdminRepository();
        $admins->admin = resetTargetAdmin();
        $otp = new FakeAdminTimedOtpRepository();

        $result = (new ResetAdminPasswordUseCase($admins, $otp))->reset('admin', 'NewPassword123!');

        expect($result->login)->toBe('admin')
            ->and($admins->passwordChanges)->toBe([
                ['adminId' => 1, 'password' => 'NewPassword123!'],
            ])
            ->and($otp->saved)->toHaveCount(1)
            ->and($otp->saved[0]['adminId'])->toBe(1)
            ->and($otp->saved[0]['otp']->toFailedAttempts())->toBe(0)
            ->and($admins->sessionVersion)->toBe(1);
    });

    it('falls back to the sole admin when no login is given', function (): void {
        $admins = new FakeAdminRepository();
        $admins->admin = resetTargetAdmin();

        $result = (new ResetAdminPasswordUseCase($admins, new FakeAdminTimedOtpRepository()))
            ->reset(null, 'NewPassword123!');

        expect($result->login)->toBe('admin');
    });

    it('throws when no matching admin exists', function (): void {
        $admins = new FakeAdminRepository();

        expect(fn () => (new ResetAdminPasswordUseCase($admins, new FakeAdminTimedOtpRepository()))
            ->reset('nobody', 'NewPassword123!'))->toThrow(AdminNotFoundException::class);
    });
});

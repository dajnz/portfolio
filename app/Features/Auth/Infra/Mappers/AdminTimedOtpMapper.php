<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Mappers;

use App\Features\Auth\Domain\Entities\AdminTimedOtp;
use App\Features\Auth\Domain\ValueObjects\RecoveryCode;
use App\Features\Auth\Domain\ValueObjects\TotpSecret;
use App\Features\Auth\Infra\Models\AdminModel;
use Illuminate\Support\Facades\Crypt;

/**
 * Converts between the `totp_*` columns of the `admins` row and the
 * `AdminTimedOtp` entity (secret decrypted; recovery codes hydrated from
 * their stored `{hash, used}` entries).
 */
final class AdminTimedOtpMapper
{
    public function toEntity(AdminModel $model): AdminTimedOtp
    {
        if (! (bool) $model->totp_enabled) {
            return AdminTimedOtp::disabled();
        }

        return AdminTimedOtp::enabled(
            secret: new TotpSecret(Crypt::decryptString($model->totp_secret)),
            recoveryCodes: array_map(
                fn (array $entry): RecoveryCode => RecoveryCode::fromStored($entry),
                $model->totp_recovery_codes ?? [],
            ),
            failedAttempts: (int) $model->totp_failed_attempts,
            lockedUntil: $model->totp_locked_until?->toDateTimeImmutable(),
        );
    }

    /**
     * Row values for all `totp_*` columns derived from the entity state.
     *
     * @return array{
     *     totp_secret: string|null,
     *     totp_recovery_codes: array<int, array{hash: string, used: bool}>|null,
     *     totp_enabled: bool,
     *     totp_failed_attempts: int,
     *     totp_locked_until: \DateTimeImmutable|null
     * }
     */
    public function toRow(AdminTimedOtp $otp): array
    {
        if (! $otp->isEnabled()) {
            return [
                'totp_secret' => null,
                'totp_recovery_codes' => null,
                'totp_enabled' => false,
                'totp_failed_attempts' => $otp->toFailedAttempts(),
                'totp_locked_until' => $otp->toLockedUntil(),
            ];
        }

        return [
            'totp_secret' => Crypt::encryptString($otp->toSecret()->toString()),
            'totp_recovery_codes' => array_map(
                fn (RecoveryCode $code): array => $code->toStored(),
                $otp->toRecoveryCodes(),
            ),
            'totp_enabled' => true,
            'totp_failed_attempts' => $otp->toFailedAttempts(),
            'totp_locked_until' => $otp->toLockedUntil(),
        ];
    }
}

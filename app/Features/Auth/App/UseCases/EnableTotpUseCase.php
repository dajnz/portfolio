<?php

declare(strict_types=1);

namespace App\Features\Auth\App\UseCases;

use App\Features\Auth\App\Services\SessionVersionSynchronizer;
use App\Features\Auth\Domain\Entities\Admin;
use App\Features\Auth\Domain\Entities\AdminTimedOtp;
use App\Features\Auth\Domain\Exceptions\InvalidTotpCodeException;
use App\Features\Auth\Domain\Exceptions\TotpEnrollmentExpiredException;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminTimedOtpRepositoryInterface;
use App\Features\Auth\Domain\ValueObjects\RecoveryCode;
use App\Features\Auth\Domain\ValueObjects\TotpSecret;
use App\Features\Auth\Infra\Services\TotpService;
use App\Features\Shared\App\BaseUseCase;
use Illuminate\Support\Facades\Session;

/**
 * TOTP enrollment via the dual-purpose `POST /admin/profile/totp-enable`
 * endpoint:
 *
 * - `start()` — generates a secret, holds it in the session
 *   (`totp_enroll.pending_secret`) and returns the QR + manual key.
 * - `verifyAndEnable()` — checks the 6-digit code against the pending
 *   secret, persists the secret (encrypted), enables TOTP, bumps the
 *   session version, and returns 10 fresh recovery codes (displayed once).
 */
final class EnableTotpUseCase extends BaseUseCase
{
    public function __construct(
        private readonly AdminTimedOtpRepositoryInterface $otpRepository,
        private readonly TotpService $totpService,
        private readonly SessionVersionSynchronizer $sessionVersion,
    ) {
    }

    /** @return array{qr_code_svg: string, manual_key: string} */
    public function start(Admin $admin): array
    {
        $secret = $this->totpService->generateSecret();
        Session::put(['totp_enroll.pending_secret' => $secret]);

        return [
            'qr_code_svg' => $this->totpService->qrCodeSvg($secret, $admin->toEmail()),
            'manual_key' => $secret,
        ];
    }

    /**
     * @return string[] the 10 recovery codes (displayed once)
     *
     * @throws InvalidTotpCodeException
     * @throws TotpEnrollmentExpiredException
     */
    public function verifyAndEnable(Admin $admin, string $code): array
    {
        $secret = Session::get('totp_enroll.pending_secret');

        if (! is_string($secret) || $secret === '') {
            throw new TotpEnrollmentExpiredException();
        }

        if (! AdminTimedOtp::isTotpCode($code) || ! $this->totpService->verify($secret, $code)) {
            throw new InvalidTotpCodeException();
        }

        $recoveryCodes = AdminTimedOtp::generateRecoveryCodes();

        $this->otpRepository->saveForAdmin(
            $admin->toId(),
            $admin->toTimedOtp()->enableWith(new TotpSecret($secret), $recoveryCodes),
        );

        Session::forget(['totp_enroll.pending_secret', 'totp.pending_admin_id', 'totp.pending_until']);
        $this->sessionVersion->bumpAndReauthenticate($admin->toId());

        return array_map(fn (RecoveryCode $code): string => $code->toDisplayCode(), $recoveryCodes);
    }
}

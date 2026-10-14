<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Services;

use App\Features\Auth\App\Contracts\TotpServiceInterface;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\Config;
use PragmaRX\Google2FA\Google2FA as Google2FAService;

/**
 * Thin wrapper over pragmarx/google2fa (+ endroid/qr-code for the
 * provisioning QR). TOTP verification uses a ±1 time-step window
 * (90-second acceptance).
 */
final class TotpService implements TotpServiceInterface
{
    private const WINDOW = 1;

    public function __construct(
        private readonly Google2FAService $google2fa,
    ) {
    }

    /** Generates a new base32 provisioning secret (16 chars). */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /** Verifies a 6-digit code within the ±1 time-step window. */
    public function verify(string $secret, string $code): bool
    {
        if (preg_match('/^\d{6}$/', $code) !== 1) {
            return false;
        }

        return (bool) $this->google2fa->verifyKey($secret, $code, self::WINDOW);
    }

    /** Current 6-digit code for a secret (used by tests and tooling). */
    public function currentOtp(string $secret): string
    {
        return (string) $this->google2fa->getCurrentOtp($secret);
    }

    /**
     * Server-rendered provisioning QR as an inline SVG string (no third-party
     * chart API — the Google Charts QR endpoint is deprecated).
     */
    public function qrCodeSvg(string $secret, string $account): string
    {
        $issuer = Config::get('app.name') ?: 'Portfolio';

        $otpauth = 'otpauth://totp/'
            .rawurlencode($issuer).':'.rawurlencode($account)
            .'?secret='.$secret
            .'&issuer='.rawurlencode($issuer)
            .'&algorithm=SHA1&digits=6&period=30';

        return (new SvgWriter())->write(new QrCode($otpauth))->getString();
    }
}

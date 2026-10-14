<?php

declare(strict_types=1);

namespace App\Features\Auth\App\Contracts;

/**
 * The app-layer TOTP contract: provisioning-secret generation, the
 * provisioning QR and code verification. An abstraction over the TOTP/QR
 * stack so the app layer never names it; the framework-backed
 * implementation lives on the infra layer.
 */
interface TotpServiceInterface
{
    /** Generates a new base32 provisioning secret. */
    public function generateSecret(): string;

    /** Verifies a 6-digit code within the acceptance window. */
    public function verify(string $secret, string $code): bool;

    /** Server-rendered provisioning QR as an inline SVG string. */
    public function qrCodeSvg(string $secret, string $account): string;
}

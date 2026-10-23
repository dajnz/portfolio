<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Services;

use App\Features\Auth\App\Contracts\TotpLockoutMessengerInterface;
use App\Features\Auth\Domain\Entities\Admin;
use App\Features\Auth\Domain\Entities\AdminTimedOtp;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminRepositoryInterface;
use App\Features\Auth\Infra\Models\AdminModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Semantic auth-state checks shared by the auth middleware and the TOTP
 * verification flow. Each method answers one named question about the
 * current request/session; the middleware layers stay thin redirect+flash
 * shells on top of these.
 */
final class AdminAuthService implements TotpLockoutMessengerInterface
{
    public function __construct(
        private readonly AdminRepositoryInterface $repository,
    ) {
    }

    /* `admin` middleware (AdminOnlyMiddleware) */

    /** Whether the authenticated user is an `AdminModel` (the sole credential table). */
    public function isAuthenticatedAdmin(): bool
    {
        return Auth::guard('web')->user() instanceof AdminModel;
    }

    /* `auth.session_version` middleware (RequireSessionVersion) */

    public function sessionVersionMatches(): bool
    {
        $user = Auth::guard('web')->user();

        return $user instanceof AdminModel
            && (int) Session::get('session_version', -1) === (int) $user->session_version;
    }

    public function forceLogout(): void
    {
        Auth::logout();
    }

    /* `auth.fingerprint` middleware (RequireSessionFingerprint) */

    public function fingerprintMatches(string $fingerprint): bool
    {
        $stored = Session::get('auth_fingerprint');

        return is_string($stored) && hash_equals($stored, $fingerprint);
    }

    /** Logs the admin out and destroys the session server-side. */
    public function forceLogoutAndInvalidate(): void
    {
        Auth::logout();
        Session::invalidate();
        Session::regenerateToken();
    }

    /* `totp.pending` middleware (RequireTotpPending) — login half-auth state */

    public function hasPendingTotpLogin(): bool
    {
        return Session::get('totp.pending_admin_id') !== null;
    }

    /** Whether the half-auth state exceeded its 5-minute expiry. */
    public function pendingTotpLoginExpired(): bool
    {
        return (int) Session::get('totp.pending_until', 0) < (new DateTimeImmutable())->getTimestamp();
    }

    /** The admin whose credentials passed verification, or null. */
    public function pendingTotpLoginAdmin(): ?Admin
    {
        $adminId = Session::get('totp.pending_admin_id');

        return $adminId === null ? null : $this->repository->findById((int) $adminId);
    }

    public function clearPendingTotpLogin(): void
    {
        Session::forget(['totp.pending_admin_id', 'totp.pending_until']);
    }

    /* `totp.pending.github_link` middleware (RequireGithubLinkTotpPending) */

    public function githubLinkPendingForCurrentAdmin(): bool
    {
        return Auth::check()
            && (int) Session::get('github_link.pending_admin_id', 0) === (int) Auth::id();
    }

    /** Whether the pending GitHub-link state exceeded its 5-minute expiry. */
    public function pendingGithubLinkExpired(): bool
    {
        return (int) Session::get('github_link.pending_until', 0) < (new DateTimeImmutable())->getTimestamp();
    }

    /** The admin who initiated the pending GitHub link, or null. */
    public function pendingGithubLinkAdmin(): ?Admin
    {
        $adminId = Session::get('github_link.pending_admin_id');

        return $adminId === null ? null : $this->repository->findById((int) $adminId);
    }

    public function clearPendingGithubLink(): void
    {
        Session::forget([
            'github_link.pending_admin_id',
            'github_link.pending_github_id',
            'github_link.pending_token',
            'github_link.pending_until',
        ]);
    }

    public function clearPendingAuthenticationState(): void
    {
        $this->clearPendingTotpLogin();
        $this->clearPendingGithubLink();
    }

    public function adminIsTotpLocked(Admin $admin): bool
    {
        return $admin->toTimedOtp()->isLocked(new DateTimeImmutable());
    }

    public function totpLockoutFlash(Admin $admin): string
    {
        $lockedUntil = $admin->toTimedOtp()->toLockedUntil();

        if ($lockedUntil === null) {
            return '';
        }

        $now = new DateTimeImmutable();

        return AdminTimedOtp::isPermanentLock($lockedUntil, $now)
            ? __('auth.totp_locked_permanent')
            : __('auth.totp_locked_minutes', ['minutes' => AdminTimedOtp::lockoutMinutesRemaining($lockedUntil, $now)]);
    }
}

<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Services;

use App\Features\Auth\Domain\Entities\Admin;
use App\Features\Auth\Domain\Entities\AdminTimedOtp;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminRepositoryInterface;
use App\Features\Auth\Infra\Models\AdminModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\Auth;

/**
 * Semantic auth-state checks shared by the auth middleware and the TOTP
 * verification flow. Each method answers one named question about the
 * current request/session; the middleware layers stay thin redirect+flash
 * shells on top of these.
 */
final class AdminAuthService
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

    /** Whether the version stored at login matches the admin's current `session_version`. */
    public function sessionVersionMatches(): bool
    {
        $user = Auth::guard('web')->user();

        return $user instanceof AdminModel
            && (int) session('session_version', -1) === (int) $user->session_version;
    }

    /** Force-logout of the current session (on version mismatch). */
    public function forceLogout(): void
    {
        Auth::logout();
    }

    /* `totp.pending` middleware (RequireTotpPending) — login half-auth state */

    /** Whether a half-auth TOTP login is in progress. */
    public function hasPendingTotpLogin(): bool
    {
        return session('totp.pending_admin_id') !== null;
    }

    /** Whether the half-auth state exceeded its 5-minute expiry. */
    public function pendingTotpLoginExpired(): bool
    {
        return (int) session('totp.pending_until', 0) < (new DateTimeImmutable())->getTimestamp();
    }

    /** The admin whose credentials passed verification, or null. */
    public function pendingTotpLoginAdmin(): ?Admin
    {
        $adminId = session('totp.pending_admin_id');

        return $adminId === null ? null : $this->repository->findById((int) $adminId);
    }

    public function clearPendingTotpLogin(): void
    {
        session()->forget(['totp.pending_admin_id', 'totp.pending_until']);
    }

    /* `totp.pending.github_link` middleware (RequireGithubLinkTotpPending) */

    /** Whether the pending GitHub-link state belongs to the authenticated admin. */
    public function githubLinkPendingForCurrentAdmin(): bool
    {
        return Auth::check()
            && (int) session('github_link.pending_admin_id', 0) === (int) Auth::id();
    }

    /** Whether the pending GitHub-link state exceeded its 5-minute expiry. */
    public function pendingGithubLinkExpired(): bool
    {
        return (int) session('github_link.pending_until', 0) < (new DateTimeImmutable())->getTimestamp();
    }

    /** The admin who initiated the pending GitHub link, or null. */
    public function pendingGithubLinkAdmin(): ?Admin
    {
        $adminId = session('github_link.pending_admin_id');

        return $adminId === null ? null : $this->repository->findById((int) $adminId);
    }

    public function clearPendingGithubLink(): void
    {
        session()->forget([
            'github_link.pending_admin_id',
            'github_link.pending_github_id',
            'github_link.pending_token',
            'github_link.pending_until',
        ]);
    }

    /* Shared */

    /** Clears both half-auth states (logout). */
    public function clearPendingAuthenticationState(): void
    {
        $this->clearPendingTotpLogin();
        $this->clearPendingGithubLink();
    }

    /** Whether the admin is currently inside a TOTP lockout. */
    public function adminIsTotpLocked(Admin $admin): bool
    {
        return $admin->toTimedOtp()->isLocked(new DateTimeImmutable());
    }

    /** Localized lockout flash message for an admin with an active lock. */
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

<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Controllers;

use App\Features\Auth\App\UseCases\LoginUseCase;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminRepositoryInterface;
use App\Features\Auth\Infra\Requests\LoginRequest;
use App\Features\Auth\Infra\Services\AdminAuthService;
use App\Features\Settings\Domain\Enums\FeatureFlagName;
use App\Features\Settings\Domain\RepositoryInterfaces\FeatureFlagRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * `GET/POST /admin/login` + `POST /admin/logout` (authentication.md:15-29,
 * 282). The login page is the single public entry to the admin section.
 */
final class LoginController
{
    public function __construct(
        private readonly LoginUseCase $loginCase,
        private readonly FeatureFlagRepositoryInterface $flags,
        private readonly AdminAuthService $auth,
        private readonly AdminRepositoryInterface $admins,
    ) {
    }

    public function create(Request $request, ResponseFactory $inertia): Response
    {
        // Prevents stale half-auth state from a previous tab (authentication.md:285).
        $this->auth->clearPendingTotpLogin();

        return $inertia->render('Auth/Admin/Pages/LoginPage', [
            // The GitHub button is shown only when the flag is ON AND at
            // least one admin account is linked (ADR-0013) — the OAuth login
            // flow can only fail otherwise.
            'github_login_available' => $this->flags->isEnabled(FeatureFlagName::GithubOauth)
                && $this->admins->hasGithubLinkedAdmin(),
            'loginPage' => true,
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $admin = $this->loginCase->attempt(
            $request->input('login', ''),
            $request->input('password', ''),
        );

        if ($admin === null) {
            return back()->withErrors(['login' => __('auth.login_failed')]);
        }

        if ($admin->toTimedOtp()->isEnabled()) {
            $this->loginCase->beginTotpVerification($admin);

            return redirect()->route('login.totp');
        }

        $this->loginCase->completeLogin($admin, (bool) $request->boolean('remember'));

        return redirect()->intended('/admin');
    }

    public function destroy(Request $request): RedirectResponse
    {
        // Handles logging out from a half-auth or pending-link session too.
        $this->auth->clearPendingAuthenticationState();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

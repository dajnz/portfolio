<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Controllers;

use App\Features\Auth\App\UseCases\GetLoginPageDataUseCase;
use App\Features\Auth\App\UseCases\LoginUseCase;
use App\Features\Auth\Infra\Requests\LoginRequest;
use App\Features\Auth\Infra\Services\AdminAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * `GET/POST /admin/login` + `POST /admin/logout`. The login page is the
 * single public entry to the admin section.
 */
final class LoginController
{
    public function __construct(
        private readonly LoginUseCase $loginCase,
        private readonly GetLoginPageDataUseCase $loginPageData,
        private readonly AdminAuthService $auth,
    ) {
    }

    public function create(Request $request, ResponseFactory $inertia): Response
    {
        // Prevents stale half-auth state from a previous tab.
        $this->auth->clearPendingTotpLogin();

        return $inertia->render('Auth/Admin/Pages/LoginPage', [
            'github_login_available' => $this->loginPageData->get()->githubLoginAvailable,
            'loginPage' => true,
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $admin = $this->loginCase->attempt(
            $request->input('login', ''),
            $request->input('password', ''),
            $request->ip(),
        );

        if ($admin === null) {
            return Redirect::back()->withErrors(['login' => __('auth.login_failed')]);
        }

        if ($admin->toTimedOtp()->isEnabled()) {
            $this->loginCase->beginTotpVerification($admin);

            return Redirect::route('login.totp');
        }

        $this->loginCase->completeLogin($admin, $request->ip(), (bool) $request->boolean('remember'));

        return Redirect::intended('/admin');
    }

    public function destroy(Request $request): RedirectResponse
    {
        // Handles logging out from a half-auth or pending-link session too.
        $this->auth->clearPendingAuthenticationState();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::route('login');
    }
}

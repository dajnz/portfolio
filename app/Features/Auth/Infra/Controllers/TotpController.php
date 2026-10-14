<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Controllers;

use App\Features\Auth\App\UseCases\VerifyGithubLinkTotpUseCase;
use App\Features\Auth\App\UseCases\VerifyLoginTotpUseCase;
use App\Features\Auth\Domain\Exceptions\GithubAlreadyLinkedException;
use App\Features\Auth\Domain\Exceptions\GithubConflictException;
use App\Features\Auth\Domain\Exceptions\GithubLinkPendingInvalidException;
use App\Features\Auth\Domain\Exceptions\InvalidTotpCodeException;
use App\Features\Auth\Domain\Exceptions\TotpLockedException;
use App\Features\Auth\Domain\Exceptions\TotpPendingInvalidException;
use App\Features\Auth\Infra\Requests\VerifyTotpRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * TOTP verification pages: the login half-auth flow
 * (`GET/POST /admin/login/totp`, gated by `totp.pending`) and the GitHub
 * link re-authentication flow (`GET/POST /admin/profile/github-link/totp`,
 * gated by `totp.pending.github_link`). Both render the same
 * `TotpVerificationPage` component. All flow logic lives in the verify use
 * cases; this controller only maps outcomes to redirects.
 */
final class TotpController
{
    public function __construct(
        private readonly VerifyLoginTotpUseCase $verifyLoginTotpCase,
        private readonly VerifyGithubLinkTotpUseCase $verifyGithubLinkTotpCase,
    ) {
    }

    public function create(ResponseFactory $inertia): Response
    {
        return $inertia->render('Auth/Admin/Pages/TotpVerificationPage', $this->pageProps());
    }

    public function createGithubLink(ResponseFactory $inertia): Response
    {
        return $inertia->render('Auth/Admin/Pages/TotpVerificationPage', $this->pageProps());
    }

    public function verify(VerifyTotpRequest $request): RedirectResponse
    {
        try {
            $this->verifyLoginTotpCase->verify($request->input('code', ''));
        } catch (TotpPendingInvalidException) {
            return Redirect::route('login');
        } catch (InvalidTotpCodeException) {
            return Redirect::back()->withErrors(['code' => __('auth.invalid_totp_code')]);
        } catch (TotpLockedException $exception) {
            return Redirect::route('login')->with('notification', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return Redirect::intended('/admin');
    }

    public function verifyGithubLink(VerifyTotpRequest $request): RedirectResponse
    {
        try {
            $this->verifyGithubLinkTotpCase->verify($request->input('code', ''));
        } catch (GithubLinkPendingInvalidException) {
            return Redirect::route('profile');
        } catch (InvalidTotpCodeException) {
            return Redirect::back()->withErrors(['code' => __('auth.invalid_totp_code')]);
        } catch (TotpLockedException $exception) {
            return Redirect::route('profile')->with('notification', ['type' => 'error', 'message' => $exception->getMessage()]);
        } catch (GithubAlreadyLinkedException) {
            return Redirect::route('profile')->with('notification', ['type' => 'error', 'message' => __('auth.github_already_linked')]);
        } catch (GithubConflictException) {
            return Redirect::route('profile')->with('notification', ['type' => 'error', 'message' => __('auth.github_conflict')]);
        }

        return Redirect::route('profile')->with('notification', ['type' => 'success', 'message' => __('auth.github_linked')]);
    }

    /**
     * @return array{lock_remaining_seconds: int|null}
     */
    private function pageProps(): array
    {
        return [
            // Always null in practice: the `totp.pending` middleware
            // redirects locked admins to the login page, so this page is
            // unreachable while a lockout is active.
            'lock_remaining_seconds' => null,
        ];
    }
}

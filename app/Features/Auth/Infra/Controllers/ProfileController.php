<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Controllers;

use App\Features\Auth\App\UseCases\DisableTotpUseCase;
use App\Features\Auth\App\UseCases\EnableTotpUseCase;
use App\Features\Auth\App\UseCases\GetAdminProfileUseCase;
use App\Features\Auth\App\UseCases\LinkGithubUseCase;
use App\Features\Auth\App\UseCases\RegenerateRecoveryCodesUseCase;
use App\Features\Auth\App\UseCases\UpdateProfileUseCase;
use App\Features\Auth\Domain\Exceptions\InvalidCurrentPasswordException;
use App\Features\Auth\Domain\Exceptions\InvalidTotpCodeException;
use App\Features\Auth\Domain\Exceptions\TotpEnrollmentExpiredException;
use App\Features\Auth\Infra\Requests\RequireCurrentPasswordRequest;
use App\Features\Auth\Infra\Requests\TotpEnableRequest;
use App\Features\Auth\Infra\Requests\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * Admin profile: profile info + password change, TOTP
 * enrollment/disable/recovery-code regeneration, GitHub unlink. All routes
 * are gated by `auth` + `auth.session_version` + `admin`. The controller
 * accepts/validates input and maps use-case outcomes to responses — the
 * flows live in the use cases.
 */
final class ProfileController
{
    public function __construct(
        private readonly GetAdminProfileUseCase $profileCase,
        private readonly UpdateProfileUseCase $updateProfileCase,
        private readonly EnableTotpUseCase $enableTotpCase,
        private readonly DisableTotpUseCase $disableTotpCase,
        private readonly RegenerateRecoveryCodesUseCase $regenerateRecoveryCodesCase,
        private readonly LinkGithubUseCase $linkGithubCase,
    ) {
    }

    public function edit(ResponseFactory $inertia): Response
    {
        $profile = $this->profileCase->get((int) Auth::id());

        return $inertia->render('Auth/Admin/Pages/ProfilePage', [
            'admin' => [
                'login' => $profile->login,
                'email' => $profile->email,
                'github_linked' => $profile->githubLinked,
                'totp_enabled' => $profile->totpEnabled,
                'recovery_codes_remaining' => $profile->recoveryCodesRemaining,
                'session_version' => $profile->sessionVersion,
            ],
            'github_oauth_enabled' => $profile->githubOauthEnabled,
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        try {
            $this->updateProfileCase->update(
                (int) $request->user()->getAuthIdentifier(),
                $request->input('name', ''),
                $request->input('email', ''),
                $request->input('login', ''),
                $request->input('password'),
                $request->input('current_password'),
            );
        } catch (InvalidCurrentPasswordException) {
            throw ValidationException::withMessages(['current_password' => __('auth.current_password_incorrect')]);
        }

        return Redirect::back()->with('notification', ['type' => 'success', 'message' => __('auth.profile_updated')]);
    }

    public function enableTotp(TotpEnableRequest $request): JsonResponse
    {
        $adminId = (int) $request->user()->getAuthIdentifier();

        if ($request->input('code') === null) {
            return ResponseFacade::json(['data' => $this->enableTotpCase->start($adminId)]);
        }

        try {
            $recoveryCodes = $this->enableTotpCase->verifyAndEnable($adminId, (string) $request->input('code'));
        } catch (InvalidTotpCodeException) {
            throw ValidationException::withMessages(['code' => __('auth.invalid_totp_code')]);
        } catch (TotpEnrollmentExpiredException) {
            throw ValidationException::withMessages(['code' => __('auth.totp_enrollment_expired')]);
        }

        return ResponseFacade::json(['data' => ['recovery_codes' => $recoveryCodes]]);
    }

    public function disableTotp(RequireCurrentPasswordRequest $request): RedirectResponse
    {
        try {
            $this->disableTotpCase->disable(
                (int) $request->user()->getAuthIdentifier(),
                $request->input('current_password', ''),
            );
        } catch (InvalidCurrentPasswordException) {
            throw ValidationException::withMessages(['current_password' => __('auth.current_password_incorrect')]);
        }

        return Redirect::back()->with('notification', ['type' => 'success', 'message' => __('auth.totp_disabled')]);
    }

    public function regenerateRecoveryCodes(RequireCurrentPasswordRequest $request): JsonResponse
    {
        try {
            $recoveryCodes = $this->regenerateRecoveryCodesCase->regenerate(
                (int) $request->user()->getAuthIdentifier(),
                $request->input('current_password', ''),
            );
        } catch (InvalidCurrentPasswordException) {
            throw ValidationException::withMessages(['current_password' => __('auth.current_password_incorrect')]);
        }

        return ResponseFacade::json(['data' => ['recovery_codes' => $recoveryCodes]]);
    }

    public function unlinkGithub(Request $request): RedirectResponse
    {
        $this->linkGithubCase->unlink(
            (int) $request->user()->getAuthIdentifier(),
        );

        return Redirect::back()->with('notification', ['type' => 'success', 'message' => __('auth.github_unlinked')]);
    }
}

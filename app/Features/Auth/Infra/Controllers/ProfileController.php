<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Controllers;

use App\Features\Auth\App\UseCases\DisableTotpUseCase;
use App\Features\Auth\App\UseCases\EnableTotpUseCase;
use App\Features\Auth\App\UseCases\LinkGithubUseCase;
use App\Features\Auth\App\UseCases\RegenerateRecoveryCodesUseCase;
use App\Features\Auth\App\UseCases\UpdateProfileUseCase;
use App\Features\Auth\Domain\Exceptions\InvalidCurrentPasswordException;
use App\Features\Auth\Domain\Exceptions\InvalidTotpCodeException;
use App\Features\Auth\Domain\Exceptions\TotpEnrollmentExpiredException;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminRepositoryInterface;
use App\Features\Auth\Infra\Requests\RequireCurrentPasswordRequest;
use App\Features\Auth\Infra\Requests\TotpEnableRequest;
use App\Features\Auth\Infra\Requests\UpdateProfileRequest;
use App\Features\Settings\Domain\Enums\FeatureFlagName;
use App\Features\Settings\Domain\RepositoryInterfaces\FeatureFlagRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * Admin profile (authentication.md:197-203,239): profile info + password
 * change, TOTP enrollment/disable/recovery-code regeneration, GitHub
 * unlink. All routes are gated by `auth` + `auth.session_version` + `admin`.
 * The controller accepts/validates input and maps use-case outcomes to
 * responses — the flows live in the use cases.
 */
final class ProfileController
{
    public function __construct(
        private readonly AdminRepositoryInterface $repository,
        private readonly UpdateProfileUseCase $updateProfile,
        private readonly EnableTotpUseCase $enableTotp,
        private readonly DisableTotpUseCase $disableTotp,
        private readonly RegenerateRecoveryCodesUseCase $regenerateRecoveryCodes,
        private readonly LinkGithubUseCase $linkGithub,
        private readonly FeatureFlagRepositoryInterface $flags,
    ) {
    }

    public function edit(ResponseFactory $inertia): Response
    {
        $admin = $this->repository->findById((int) Auth::id());

        return $inertia->render('Auth/Admin/Pages/ProfilePage', [
            'admin' => [
                'login' => $admin->toLogin(),
                'email' => $admin->toEmail(),
                'github_linked' => $admin->isGithubLinked(),
                'totp_enabled' => $admin->toTimedOtp()->isEnabled(),
                'session_version' => $admin->toSessionVersion(),
            ],
            'github_oauth_enabled' => $this->flags->isEnabled(FeatureFlagName::GithubOauth),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        try {
            $this->updateProfile->update(
                $this->repository->findById((int) $request->user()->getAuthIdentifier()),
                $request->input('name', ''),
                $request->input('email', ''),
                $request->input('login', ''),
                $request->input('password'),
                $request->input('current_password'),
            );
        } catch (InvalidCurrentPasswordException) {
            throw ValidationException::withMessages(['current_password' => __('auth.current_password_incorrect')]);
        }

        return back()->with('success', __('auth.profile_updated'));
    }

    public function enableTotp(TotpEnableRequest $request): JsonResponse
    {
        $admin = $this->repository->findById((int) $request->user()->getAuthIdentifier());

        if ($request->input('code') === null) {
            return response()->json(['data' => $this->enableTotp->start($admin)]);
        }

        try {
            $recoveryCodes = $this->enableTotp->verifyAndEnable($admin, (string) $request->input('code'));
        } catch (InvalidTotpCodeException) {
            throw ValidationException::withMessages(['code' => __('auth.invalid_totp_code')]);
        } catch (TotpEnrollmentExpiredException) {
            throw ValidationException::withMessages(['code' => __('auth.totp_enrollment_expired')]);
        }

        return response()->json(['data' => ['recovery_codes' => $recoveryCodes]]);
    }

    public function disableTotp(RequireCurrentPasswordRequest $request): RedirectResponse
    {
        try {
            $this->disableTotp->disable(
                $this->repository->findById((int) $request->user()->getAuthIdentifier()),
                $request->input('current_password', ''),
            );
        } catch (InvalidCurrentPasswordException) {
            throw ValidationException::withMessages(['current_password' => __('auth.current_password_incorrect')]);
        }

        return back()->with('success', __('auth.totp_disabled'));
    }

    public function regenerateRecoveryCodes(RequireCurrentPasswordRequest $request): JsonResponse
    {
        try {
            $recoveryCodes = $this->regenerateRecoveryCodes->regenerate(
                $this->repository->findById((int) $request->user()->getAuthIdentifier()),
                $request->input('current_password', ''),
            );
        } catch (InvalidCurrentPasswordException) {
            throw ValidationException::withMessages(['current_password' => __('auth.current_password_incorrect')]);
        }

        return response()->json(['data' => ['recovery_codes' => $recoveryCodes]]);
    }

    public function unlinkGithub(Request $request): RedirectResponse
    {
        $this->linkGithub->unlink(
            $this->repository->findById((int) $request->user()->getAuthIdentifier()),
        );

        return back()->with('success', __('auth.github_unlinked'));
    }
}

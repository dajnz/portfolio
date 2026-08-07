<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Controllers;

use App\Features\Auth\App\UseCases\HandleGithubCallbackUseCase;
use App\Features\Auth\App\UseCases\LinkGithubUseCase;
use App\Features\Auth\Domain\Enums\GithubLinkOutcome;
use App\Features\Auth\Domain\Enums\GithubLoginOutcome;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * GitHub OAuth (authentication.md:116-191): one callback handles both the
 * login intent (unauthenticated) and the link intent (authenticated),
 * communicated via the `oauth_intent` session flag. The intents are
 * executed by the use cases; this controller only maps outcomes to
 * redirects.
 */
final class GithubController
{
    public function __construct(
        private readonly AdminRepositoryInterface $repository,
        private readonly HandleGithubCallbackUseCase $githubCallbackCase,
        private readonly LinkGithubUseCase $linkGithubCase,
    ) {
    }

    public function redirect(Request $request): RedirectResponse
    {
        $request->session()->put('oauth_intent', 'login');

        return Socialite::driver('github')->redirect();
    }

    public function linkRedirect(Request $request): RedirectResponse
    {
        $request->session()->put('oauth_intent', 'link');

        return Socialite::driver('github')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $intent = $request->session()->pull('oauth_intent');
        $socialiteUser = Socialite::driver('github')->user();

        return match ($intent) {
            'login' => $this->handleLoginIntent($socialiteUser),
            'link' => $this->handleLinkIntent($socialiteUser),
            default => redirect()->route('login')->with('notification', ['type' => 'error', 'message' => __('auth.github_invalid_intent')]),
        };
    }

    private function handleLoginIntent(SocialiteUser $socialiteUser): RedirectResponse
    {
        // Logic bug guard (authentication.md:133): authenticated user with a login intent.
        if (Auth::check()) {
            abort(400, 'Authenticated user with login OAuth intent.');
        }

        return match ($this->githubCallbackCase->handle((string) $socialiteUser->getId())) {
            GithubLoginOutcome::NoAccount => redirect()->route('login')
                ->with('notification', ['type' => 'error', 'message' => __('auth.github_no_account')]),
            GithubLoginOutcome::TotpRequired => redirect()->route('login.totp'),
            GithubLoginOutcome::Authenticated => redirect()->intended('/admin'),
        };
    }

    private function handleLinkIntent(SocialiteUser $socialiteUser): RedirectResponse
    {
        // Session expired mid-flow (authentication.md:187).
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $admin = $this->repository->findById((int) Auth::id());

        if ($admin === null) {
            return redirect()->route('login');
        }

        return match ($this->linkGithubCase->linkOrPrepare($admin, (string) $socialiteUser->getId(), (string) $socialiteUser->token)) {
            GithubLinkOutcome::AlreadyLinked => redirect()->route('profile')->with('notification', ['type' => 'error', 'message' => __('auth.github_already_linked')]),
            GithubLinkOutcome::Conflict => redirect()->route('profile')->with('notification', ['type' => 'error', 'message' => __('auth.github_conflict')]),
            GithubLinkOutcome::TotpRequired => redirect()->route('profile.github-link.totp'),
            GithubLinkOutcome::Linked => redirect()->route('profile')->with('notification', ['type' => 'success', 'message' => __('auth.github_linked')]),
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Controllers;

use App\Features\Auth\App\UseCases\HandleGithubCallbackUseCase;
use App\Features\Auth\App\UseCases\LinkGithubUseCase;
use App\Features\Auth\Domain\Enums\GithubLinkOutcome;
use App\Features\Auth\Domain\Enums\GithubLoginOutcome;
use App\Features\Auth\Domain\Exceptions\AdminNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * GitHub OAuth: one callback handles both the login intent
 * (unauthenticated) and the link intent (authenticated), communicated via
 * the `oauth_intent` session flag. The intents are executed by the use
 * cases; this controller only maps outcomes to redirects.
 */
final class GithubController
{
    public function __construct(
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
            'login' => $this->handleLoginIntent($socialiteUser, $request->ip()),
            'link' => $this->handleLinkIntent($socialiteUser),
            default => Redirect::route('login')->with('notification', ['type' => 'error', 'message' => __('auth.github_invalid_intent')]),
        };
    }

    private function handleLoginIntent(SocialiteUser $socialiteUser, string $ip): RedirectResponse
    {
        // Guard against an authenticated user with a login intent.
        if (Auth::check()) {
            abort(400, 'Authenticated user with login OAuth intent.');
        }

        return match ($this->githubCallbackCase->handle((string) $socialiteUser->getId(), $ip)) {
            GithubLoginOutcome::NoAccount => Redirect::route('login')
                ->with('notification', ['type' => 'error', 'message' => __('auth.github_no_account')]),
            GithubLoginOutcome::TotpRequired => Redirect::route('login.totp'),
            GithubLoginOutcome::Authenticated => Redirect::intended('/admin'),
        };
    }

    private function handleLinkIntent(SocialiteUser $socialiteUser): RedirectResponse
    {
        // Session expired mid-flow.
        if (! Auth::check()) {
            return Redirect::route('login');
        }

        try {
            $outcome = $this->linkGithubCase->linkOrPrepare((int) Auth::id(), (string) $socialiteUser->getId(), (string) $socialiteUser->token);
        } catch (AdminNotFoundException) {
            return Redirect::route('login');
        }

        return match ($outcome) {
            GithubLinkOutcome::AlreadyLinked => Redirect::route('profile')->with('notification', ['type' => 'error', 'message' => __('auth.github_already_linked')]),
            GithubLinkOutcome::Conflict => Redirect::route('profile')->with('notification', ['type' => 'error', 'message' => __('auth.github_conflict')]),
            GithubLinkOutcome::TotpRequired => Redirect::route('profile.github-link.totp'),
            GithubLinkOutcome::Linked => Redirect::route('profile')->with('notification', ['type' => 'success', 'message' => __('auth.github_linked')]),
        };
    }
}

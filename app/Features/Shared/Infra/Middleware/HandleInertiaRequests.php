<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Middleware;

use App\Features\Auth\Domain\Entities\Admin;
use App\Features\Auth\Infra\Mappers\AdminMapper;
use App\Features\Auth\Infra\Models\AdminModel;
use App\Features\Settings\Domain\Enums\FeatureFlagName;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\FeatureFlagRepositoryInterface;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use App\Features\Shared\Infra\Services\ActiveLocaleService;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Throwable;
use Tighten\Ziggy\Ziggy;

/**
 * Inertia shared props (docs/openapi/inertia/shared.yaml + error-handling.md:
 * 266-273). Runs after the `locale` / `locale.path` / `theme` middlewares
 * in the `web` group and reads their resolved values from the request
 * attributes. DB-backed props (feature flags, settings) degrade to their
 * defaults when the tables are unavailable.
 */
final class HandleInertiaRequests extends Middleware
{
    public function __construct(
        private readonly AdminMapper $adminMapper,
        private readonly SettingRepositoryInterface $settings,
        private readonly FeatureFlagRepositoryInterface $flags,
        private readonly ActiveLocaleService $locales,
    ) {
    }

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'locale' => app()->getLocale(),
            // The active locales (the `supported_locales` setting) — the
            // language selector and the per-locale editing tabs only show
            // these; disabled locales are not editable nor deleted
            // (5.2 rework).
            'supported_locales' => $this->locales->activeLocales(),
            'theme' => $request->attributes->get('theme', 'light'),
            'auth' => [
                'user' => $this->adminUser(),
            ],
            'ziggy' => fn (): array => (new Ziggy())->toArray(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'notification' => $request->session()->get('notification'),
            'cspNonce' => app()->bound(CspNonce::class) ? app(CspNonce::class)->getValue() : '',
            'featureFlags' => $this->featureFlags(),
            'settings' => [
                'locale' => $request->attributes->get('locale', app()->getLocale()),
                'theme' => $request->attributes->get('theme', 'light'),
                'items_per_page' => $this->settings->value(SettingName::ItemsPerPage, config('portfolio.items_per_page', 12)),
            ],
            'request_id' => $request->attributes->get('request_id'),
        ]);
    }

    private function adminUser(): ?array
    {
        $model = auth()->user();

        if (! $model instanceof AdminModel) {
            return null;
        }

        /** @var Admin $admin */
        $admin = $this->adminMapper->toEntity($model);
        $timedOtp = $admin->toTimedOtp();

        return [
            'id' => $admin->toId(),
            'name' => $admin->toName(),
            'email' => $admin->toEmail(),
            'login' => $admin->toLogin(),
            'github_linked' => $admin->isGithubLinked(),
            'totp_enabled' => $timedOtp->isEnabled(),
            'is_oauth_primary' => $admin->isOauthPrimary(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function featureFlags(): array
    {
        try {
            $flags = [];
            foreach ($this->flags->findAll() as $flag) {
                $flags[$flag->toName()] = $flag->toEnabled();
            }

            foreach (FeatureFlagName::cases() as $name) {
                $flags[$name->value] ??= false;
            }

            return $flags;
        } catch (Throwable) {
            return [];
        }
    }
}

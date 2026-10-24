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
use App\Features\Shared\Domain\RepositoryInterfaces\AboutInfoReadRepositoryInterface;
use App\Features\Shared\Domain\RepositoryInterfaces\ContactReadRepositoryInterface;
use App\Features\Shared\App\Services\PublicationTimeZoneResolver;
use App\Features\Shared\Infra\Services\ActiveLocaleService;
use App\Features\Shared\Infra\Services\AppVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use Inertia\Middleware;
use Throwable;
use Tighten\Ziggy\Ziggy;

/**
 * Inertia shared props. Runs after the `locale` / `locale.path` / `theme`
 * middlewares in the `web` group and reads their resolved values from the
 * request attributes. DB-backed props (feature flags, settings) degrade to
 * their defaults when the tables are unavailable.
 */
final class HandleInertiaRequests extends Middleware
{
    public function __construct(
        private readonly AdminMapper $adminMapper,
        private readonly SettingRepositoryInterface $settings,
        private readonly FeatureFlagRepositoryInterface $flags,
        private readonly AboutInfoReadRepositoryInterface $aboutInfo,
        private readonly ContactReadRepositoryInterface $contacts,
        private readonly ActiveLocaleService $locales,
        private readonly AppVersion $appVersion,
        private readonly PublicationTimeZoneResolver $publicationTimeZone,
    ) {
    }

    public function share(Request $request): array
    {
        $locale = App::getLocale();
        $ownerName = $this->aboutInfo->findSiteName($locale);

        return array_merge(parent::share($request), [
            'locale' => $locale,
            'about_timezone' => $this->publicationTimeZone->resolve(),
            // Only active locales (the `supported_locales` setting) are shown
            // in the language selector and per-locale editing tabs; disabled
            // locales are neither editable nor deleted.
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
            'cspNonce' => App::bound(CspNonce::class) ? App::make(CspNonce::class)->getValue() : '',
            'featureFlags' => $this->featureFlags(),
            'copyright' => [
                'owner_name' => $ownerName,
            ],
            'version' => $this->appVersion->value(),
            'console_greeting' => $this->consoleGreeting($locale, $ownerName),
            'settings' => [
                'locale' => $request->attributes->get('locale', App::getLocale()),
                'theme' => $request->attributes->get('theme', 'light'),
                'items_per_page' => $this->settings->value(SettingName::ItemsPerPage, Config::get('portfolio.items_per_page', 12)),
            ],
            'request_id' => $request->attributes->get('request_id'),
        ]);
    }

    private function adminUser(): ?array
    {
        $model = Auth::user();

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

    /**
     * The console greeting rendered by the public layout on every public
     * page: localized greeting text plus the locale-resolved owner
     * name/title and the contacts as label/value pairs.
     *
     * @return array{
     *     greeting: string,
     *     name: string,
     *     position: string,
     *     contacts: array<int, array{label: string, value: string}>,
     * }
     */
    private function consoleGreeting(string $locale, string $ownerName): array
    {
        return [
            'greeting' => Lang::string('landing.console_greeting'),
            'name' => $ownerName,
            'position' => $this->aboutInfo->findSiteTitle($locale),
            'contacts' => array_map(
                static fn (array $contact): array => [
                    'label' => $contact['name'],
                    'value' => $contact['value'],
                ],
                $this->contacts->findAll($locale),
            ),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;

/**
 * Locale helpers for the Infra/app layers: the active locales come from
 * the `supported_locales` setting (falling back to the committed
 * `config('portfolio.supported_locales')`), normalized to a deduplicated
 * list of strings. Shared by the per-locale form requests, the
 * translation-building use cases and the SEO helpers (content-management.
 * md:419).
 */
final class LocaleService
{
    public function __construct(
        private readonly SettingRepositoryInterface $settings,
    ) {
    }

    /**
     * @return string[]
     */
    public function activeLocales(): array
    {
        $locales = $this->settings->value(
            SettingName::SupportedLocales,
            config('portfolio.supported_locales', ['en', 'ru']),
        );

        return array_values(array_unique(array_map('strval', (array) $locales)));
    }

    public function isActive(string $locale): bool
    {
        return in_array($locale, $this->activeLocales(), true);
    }
}

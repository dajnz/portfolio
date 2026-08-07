<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\UseCases;

use App\Features\Portfolio\Domain\Entities\AboutInfo;
use App\Features\Portfolio\Domain\Entities\AboutInfoTranslation;
use App\Features\Portfolio\Domain\RepositoryInterfaces\AboutInfoRepositoryInterface;
use App\Features\Portfolio\Domain\ValueObjects\AvailabilityStatus;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Updates the single-row about aggregate (id = 1): the non-translatable
 * columns plus the per-locale translation rows (upsert; rows for locales
 * absent from the submission are preserved, per content-management.md:
 * 419 — only locales active in the `supported_locales` setting are
 * touched).
 */
final class UpdateAboutUseCase
{
    public function __construct(
        private readonly AboutInfoRepositoryInterface $repository,
        private readonly SettingRepositoryInterface $settings,
    ) {
    }

    /**
     * @param  array<string, string|null>  $photoAlt  Per-locale alt text.
     * @param  array<string, array{name: string, title: string, short_pitch: string, location: string|null}>  $translations
     */
    public function update(
        int $id,
        ?string $photo,
        array $photoAlt,
        bool $showLocation,
        ?string $timezone,
        bool $relocationReadiness,
        ?AvailabilityStatus $availabilityStatus,
        string $workType,
        array $translations,
    ): void {
        $activeLocales = $this->settings->value(
            SettingName::SupportedLocales,
            config('portfolio.supported_locales', ['en', 'ru']),
        );

        $entities = [];
        foreach ((array) $activeLocales as $locale) {
            $values = $translations[$locale] ?? null;

            if ($values === null) {
                continue;
            }

            $entities[] = new AboutInfoTranslation(
                locale: $locale,
                name: $values['name'],
                title: $values['title'],
                shortPitch: $values['short_pitch'],
                location: $values['location'] ?? null,
            );
        }

        $aboutInfo = new AboutInfo(
            id: $id,
            photo: $photo,
            photoAlt: $photoAlt,
            showLocation: $showLocation,
            timezone: $timezone,
            relocationReadiness: $relocationReadiness,
            availabilityStatus: $availabilityStatus,
            workType: $workType,
            translations: $entities,
        );

        DB::transaction(function () use ($aboutInfo): void {
            $this->repository->save($aboutInfo);
        });
    }
}

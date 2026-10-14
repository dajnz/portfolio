<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\UseCases;

use App\Features\Portfolio\Domain\Entities\AboutInfo;
use App\Features\Portfolio\Domain\RepositoryInterfaces\AboutInfoRepositoryInterface;
use App\Features\Portfolio\Domain\ValueObjects\AboutInfoTranslation;
use App\Features\Portfolio\Domain\ValueObjects\AvailabilityStatus;
use App\Features\Portfolio\Domain\ValueObjects\WorkType;
use App\Features\Shared\App\Contracts\ActiveLocaleInterface;
use App\Features\Shared\App\Contracts\TransactionManagerInterface;
use App\Features\Shared\Domain\ValueObjects\StoredPicture;

/**
 * Updates the single-row about aggregate (id = 1): the non-translatable
 * columns plus the per-locale translation rows (upsert; rows for locales
 * absent from the submission are preserved — only locales active in the
 * `supported_locales` setting are touched).
 */
final class UpdateAboutUseCase
{
    public function __construct(
        private readonly AboutInfoRepositoryInterface $repository,
        private readonly ActiveLocaleInterface $locales,
        private readonly TransactionManagerInterface $transactionManager,
    ) {
    }

    /**
     * @param  array<string, array{name: string, title: string, short_pitch: string, location: string|null}>  $translations
     */
    public function update(
        int $id,
        ?StoredPicture $photo,
        bool $showLocation,
        ?string $timezone,
        bool $relocationReadiness,
        ?AvailabilityStatus $availabilityStatus,
        WorkType $workType,
        array $translations,
    ): void {
        $entities = [];
        foreach ($this->locales->activeLocales() as $locale) {
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
            showLocation: $showLocation,
            timezone: $timezone,
            relocationReadiness: $relocationReadiness,
            availabilityStatus: $availabilityStatus,
            workType: $workType,
            translations: $entities,
        );

        $this->transactionManager->transaction(function () use ($aboutInfo): void {
            $this->repository->save($aboutInfo);
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Features\Resume\App\UseCases;

use App\Features\Resume\App\DTOs\ResumeEditDataDto;
use App\Features\Resume\Domain\RepositoryInterfaces\ResumeConfigRepositoryInterface;
use App\Features\Resume\Infra\Repositories\ResumeDataReaderInterface;
use App\Features\Shared\Infra\Services\ActiveLocaleService;

/**
 * Reads the resume configuration for the admin edit form: mode, layout,
 * manual file path, the per-locale about texts and the inclusion counts.
 * When the (seeder-guaranteed) single row is absent, the defaults are
 * returned — the page is the creation surface.
 */
final class GetResumeForEditUseCase
{
    public function __construct(
        private readonly ResumeConfigRepositoryInterface $repository,
        private readonly ResumeDataReaderInterface $dataReader,
        private readonly ActiveLocaleService $locales,
    ) {
    }

    public function get(): ResumeEditDataDto
    {
        $config = $this->repository->find();
        $counts = $this->dataReader->inclusionCounts();

        if ($config === null) {
            return new ResumeEditDataDto(
                mode: 'manual',
                layout: self::defaultLayout(),
                customResumeFile: null,
                aboutTexts: [],
                includedSkillsCount: $counts['skills'],
                includedExperienceCount: $counts['experience'],
                includedContactsCount: $counts['contacts'],
            );
        }

        $aboutTexts = [];

        foreach ($this->locales->activeLocales() as $locale) {
            $aboutTexts[$locale] = $config->aboutTextFor($locale) ?? '';
        }

        return new ResumeEditDataDto(
            mode: $config->toMode(),
            layout: $config->toLayout(),
            customResumeFile: $config->toCustomResumeFile(),
            aboutTexts: $aboutTexts,
            includedSkillsCount: $counts['skills'],
            includedExperienceCount: $counts['experience'],
            includedContactsCount: $counts['contacts'],
        );
    }

    /**
     * The default layout, normalized to the validated `order` range 0–4
     * (the request enforces `between:0,4`).
     *
     * @return array<string, mixed>
     */
    private static function defaultLayout(): array
    {
        return [
            'template' => 'modern',
            'sections' => [
                'header' => ['visible' => true, 'order' => 0],
                'about_me' => ['visible' => true, 'order' => 1],
                'skills' => ['visible' => true, 'order' => 2],
                'experience' => ['visible' => true, 'order' => 3],
                'contacts' => ['visible' => true, 'order' => 4],
            ],
        ];
    }
}

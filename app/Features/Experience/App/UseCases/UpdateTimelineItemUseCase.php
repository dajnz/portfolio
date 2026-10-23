<?php

declare(strict_types=1);

namespace App\Features\Experience\App\UseCases;

use App\Features\Experience\App\Services\TimelineContinuityService;
use App\Features\Experience\Domain\Entities\TimelineItem;
use App\Features\Experience\Domain\Entities\TimelineItemTranslation;
use App\Features\Experience\Domain\RepositoryInterfaces\TimelineItemRepositoryInterface;
use App\Features\Experience\Domain\ValueObjects\RelatedProject;
use App\Features\Experience\Domain\ValueObjects\WorkPeriod;
use App\Features\Shared\App\Contracts\ActiveLocaleInterface;
use App\Features\Shared\App\Contracts\TransactionManagerInterface;
use App\Features\Shared\Domain\Entities\TechSkillTag;
use App\Features\Shared\Domain\RepositoryInterfaces\TechSkillReadRepositoryInterface;
use App\Features\Shared\Domain\ValueObjects\SkillIcon;
use App\Features\Shared\Domain\ValueObjects\SortOrder;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;
use DateTimeImmutable;

/**
 * Updates a timeline item — same shape as {@see StoreTimelineItemUseCase}
 * (incl. the continuity rules via {@see TimelineContinuityService}, the
 * item's own row excluded from the predecessor/successor lookups) with the
 * item's identity. Translations are upserted for the active locales only;
 * rows of locales disabled in the settings are preserved untouched, and
 * the tech-tag pivot is re-synced from the submitted IDs.
 */
final class UpdateTimelineItemUseCase
{
    public function __construct(
        private readonly TimelineItemRepositoryInterface $repository,
        private readonly TechSkillReadRepositoryInterface $skills,
        private readonly ActiveLocaleInterface $locales,
        private readonly TimelineContinuityService $continuity,
        private readonly TransactionManagerInterface $transactionManager,
        private readonly CountCacheInvalidatorInterface $countCacheInvalidator,
    ) {
    }

    /**
     * @param  array<string, string>  $position  Locale => position.
     * @param  array<string, string>  $companyName  Locale => company name.
     * @param  array<string, string|null>  $companyCountry  Locale => country.
     * @param  array<string, string>  $shortDescription  Locale => Markdown text.
     * @param  array<int, array{description: array<string, string>}>  $achievements  Per-locale descriptions.
     * @param  int[]  $techTagIds  Skill IDs for the pivot.
     */
    public function update(
        int $id,
        array $position,
        array $companyName,
        array $companyCountry,
        array $shortDescription,
        array $achievements,
        array $techTagIds,
        DateTimeImmutable $startedAt,
        ?DateTimeImmutable $endedAt,
        bool $isPublished,
        int $sortOrder,
        ?int $projectId,
        bool $includeInResume,
        ?string $externalProjectName,
        ?string $externalProjectUrl,
    ): void {
        $this->transactionManager->transaction(function () use (
            $id,
            $position,
            $companyName,
            $companyCountry,
            $shortDescription,
            $achievements,
            $techTagIds,
            $startedAt,
            $endedAt,
            $isPublished,
            $sortOrder,
            $projectId,
            $includeInResume,
            $externalProjectName,
            $externalProjectUrl,
        ): void {
            $this->continuity->assertContinuity($startedAt, $endedAt, $id);

            $this->repository->save(new TimelineItem(
                id: $id,
                period: new WorkPeriod($startedAt, $endedAt),
                isPublished: $isPublished,
                sortOrder: new SortOrder($sortOrder),
                relatedProject: $this->relatedProject(
                    $projectId,
                    $externalProjectName,
                    $externalProjectUrl,
                ),
                includeInResume: $includeInResume,
                translations: $this->translations(
                    $position,
                    $companyName,
                    $companyCountry,
                    $shortDescription,
                    $achievements,
                ),
                techTags: $this->techTags($techTagIds),
            ));
        });

        $this->countCacheInvalidator->invalidate('experience');
    }

    private function relatedProject(
        ?int $projectId,
        ?string $externalProjectName,
        ?string $externalProjectUrl,
    ): ?RelatedProject {
        if ($projectId === null && $externalProjectName === null) {
            return null;
        }

        return new RelatedProject(
            ownProjectId: $projectId,
            externalProjectName: $externalProjectName,
            externalProjectUrl: $externalProjectUrl,
        );
    }

    /**
     * @param  int[]  $techTagIds
     * @return TechSkillTag[]
     */
    private function techTags(array $techTagIds): array
    {
        return array_map(
            static fn ($skill): TechSkillTag => new TechSkillTag(
                id: $skill->toId(),
                skillName: $skill->toName(),
                icon: $skill->toIcon() === null ? null : new SkillIcon($skill->toIcon()),
            ),
            $this->skills->findByIds($techTagIds, $this->locales->current()),
        );
    }

    /**
     * @param  array<string, string>  $position
     * @param  array<string, string>  $companyName
     * @param  array<string, string|null>  $companyCountry
     * @param  array<string, string>  $shortDescription
     * @param  array<int, array{description: array<string, string>}>  $achievements
     * @return TimelineItemTranslation[]
     */
    private function translations(
        array $position,
        array $companyName,
        array $companyCountry,
        array $shortDescription,
        array $achievements,
    ): array {
        $translations = [];

        foreach ($this->locales->activeLocales() as $locale) {
            $translations[] = new TimelineItemTranslation(
                locale: $locale,
                position: $position[$locale] ?? '',
                companyName: $companyName[$locale] ?? '',
                companyCountry: $companyCountry[$locale] ?? null,
                shortDescription: $shortDescription[$locale] ?? '',
                achievements: $this->achievementsFor($achievements, $locale),
            );
        }

        return $translations;
    }

    /**
     * @param  array<int, array{description: array<string, string>}>  $achievements
     * @return array<int, array{description: string}>
     */
    private function achievementsFor(array $achievements, string $locale): array
    {
        $result = [];

        foreach ($achievements as $achievement) {
            $description = $achievement['description'][$locale] ?? null;

            if (! is_string($description) || $description === '') {
                continue;
            }

            $result[] = ['description' => $description];
        }

        return $result;
    }
}

<?php

declare(strict_types=1);

namespace App\Features\Experience\App\UseCases;

use App\Features\Experience\Domain\Entities\TimelineItem;
use App\Features\Experience\Domain\Entities\TimelineItemTranslation;
use App\Features\Experience\Domain\RepositoryInterfaces\TimelineItemRepositoryInterface;
use App\Features\Experience\Domain\ValueObjects\RelatedProject;
use App\Features\Experience\Domain\ValueObjects\WorkPeriod;
use App\Features\Shared\Domain\Entities\TechSkillTag;
use App\Features\Shared\Domain\RepositoryInterfaces\TechSkillReadRepositoryInterface;
use App\Features\Shared\Domain\ValueObjects\SkillIcon;
use App\Features\Shared\Domain\ValueObjects\SortOrder;
use App\Features\Shared\Infra\Services\ActiveLocaleService;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Updates a timeline item — same shape as {@see StoreTimelineItemUseCase}
 * with the item's identity. Translations are upserted for the active
 * locales only; rows of locales disabled in the settings are preserved
 * untouched (content-management.md:419), the tech-tag pivot is
 * re-synced from the submitted IDs (A2-style reconciliation by the
 * sync itself).
 */
final class UpdateTimelineItemUseCase
{
    public function __construct(
        private readonly TimelineItemRepositoryInterface $repository,
        private readonly TechSkillReadRepositoryInterface $skills,
        private readonly ActiveLocaleService $locales,
    ) {
    }

    /**
     * @param  array<string, string>  $position  Locale => position.
     * @param  array<string, string>  $companyName  Locale => company name.
     * @param  array<string, string|null>  $companyCountry  Locale => country.
     * @param  array<string, string>  $shortDescription  Locale => Markdown text.
     * @param  array<int, array{description: array<string, string>, project_link: string|null}>  $achievements  Per-locale descriptions.
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
        bool $isCurrent,
        bool $isPublished,
        int $sortOrder,
        ?int $projectId,
        bool $includeInResume,
        ?string $externalProjectName,
    ): void {
        DB::transaction(function () use (
            $id,
            $position,
            $companyName,
            $companyCountry,
            $shortDescription,
            $achievements,
            $techTagIds,
            $startedAt,
            $endedAt,
            $isCurrent,
            $isPublished,
            $sortOrder,
            $projectId,
            $includeInResume,
            $externalProjectName,
        ): void {
            $this->repository->save(new TimelineItem(
                id: $id,
                period: new WorkPeriod($startedAt, $isCurrent ? null : $endedAt),
                isPublished: $isPublished,
                sortOrder: new SortOrder($sortOrder),
                relatedProject: $this->relatedProject($projectId, $externalProjectName),
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
    }

    private function relatedProject(?int $projectId, ?string $externalProjectName): ?RelatedProject
    {
        if ($projectId === null && $externalProjectName === null) {
            return null;
        }

        return new RelatedProject(
            ownProjectId: $projectId,
            externalProjectName: $externalProjectName,
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
            $this->skills->findByIds($techTagIds, app()->getLocale()),
        );
    }

    /**
     * @param  array<string, string>  $position
     * @param  array<string, string>  $companyName
     * @param  array<string, string|null>  $companyCountry
     * @param  array<string, string>  $shortDescription
     * @param  array<int, array{description: array<string, string>, project_link: string|null}>  $achievements
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
     * @param  array<int, array{description: array<string, string>, project_link: string|null}>  $achievements
     * @return array<int, array{description: string, project_link: string|null}>
     */
    private function achievementsFor(array $achievements, string $locale): array
    {
        $result = [];

        foreach ($achievements as $achievement) {
            $description = $achievement['description'][$locale] ?? null;

            if (! is_string($description) || $description === '') {
                continue;
            }

            $result[] = [
                'description' => $description,
                'project_link' => $achievement['project_link'] ?? null,
            ];
        }

        return $result;
    }
}

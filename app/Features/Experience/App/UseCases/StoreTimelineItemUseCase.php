<?php

declare(strict_types=1);

namespace App\Features\Experience\App\UseCases;

use App\Features\Experience\App\Services\TimelineContinuityService;
use App\Features\Experience\Domain\Entities\TimelineItem;
use App\Features\Experience\Domain\Entities\TimelineItemTranslation;
use App\Features\Experience\Domain\RepositoryInterfaces\TimelineItemRepositoryInterface;
use App\Features\Experience\Domain\ValueObjects\RelatedProject;
use App\Features\Experience\Domain\ValueObjects\WorkPeriod;
use App\Features\Shared\App\Contracts\TransactionManagerInterface;
use App\Features\Shared\Domain\Entities\TechSkillTag;
use App\Features\Shared\Domain\RepositoryInterfaces\TechSkillReadRepositoryInterface;
use App\Features\Shared\Domain\ValueObjects\SkillIcon;
use App\Features\Shared\Domain\ValueObjects\SortOrder;
use App\Features\Shared\Infra\Services\ActiveLocaleService;
use App\Features\Shared\Infra\Services\AdminCursorPaginator;
use DateTimeImmutable;
use Illuminate\Support\Facades\App;

/**
 * Creates a timeline item: the {@see WorkPeriod} (a null `ended_at` =
 * ongoing), the {@see RelatedProject} (null when no project reference),
 * the {@see SortOrder}, the tech tags resolved from their IDs, and the
 * per-locale translations — every ACTIVE locale gets a row, since
 * `short_description` and `position` are required per locale; the
 * company pair (name + country) is optional but all-or-none.
 *
 * Timeline continuity (the "continuous career" model) is asserted by
 * {@see TimelineContinuityService} inside the same transaction as the
 * save so the auto-filled predecessor and the new row land atomically.
 * Returns the created item's id so the controller can redirect to its
 * edit page (a create must not leave the form in create mode — a
 * second save would insert another item).
 */
final class StoreTimelineItemUseCase
{
    public function __construct(
        private readonly TimelineItemRepositoryInterface $repository,
        private readonly TechSkillReadRepositoryInterface $skills,
        private readonly ActiveLocaleService $locales,
        private readonly TimelineContinuityService $continuity,
        private readonly TransactionManagerInterface $transactionManager,
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
    public function store(
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
    ): int {
        $itemId = $this->transactionManager->transaction(function () use (
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
        ): int {
            $this->continuity->assertContinuity($startedAt, $endedAt, null);

            return $this->repository->save(new TimelineItem(
                id: 0,
                period: new WorkPeriod($startedAt, $endedAt),
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

        AdminCursorPaginator::invalidateCountCache('experience');

        return $itemId;
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
     * Resolves the submitted skill IDs into their light tags (the
     * entity's aggregate carries the names/icons for display). The
     * `exists:skills,id` request rule guarantees they exist.
     *
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
            $this->skills->findByIds($techTagIds, App::getLocale()),
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

        // Every ACTIVE locale gets a row — `short_description` is
        // required per locale.
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
     * Only the achievements carrying a description for the locale are
     * stored in that locale's jsonb column — an entry may legitimately
     * describe an achievement in one language only.
     *
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

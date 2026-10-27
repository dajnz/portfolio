<?php

declare(strict_types=1);

namespace App\Features\Experience\Infra\Controllers;

use App\Features\Experience\App\UseCases\GetPublicTimelineUseCase;
use App\Features\Experience\Domain\Entities\TimelineItem;
use App\Features\Shared\App\Contracts\MarkdownContentPipelineInterface;
use App\Features\Shared\Domain\Entities\TechSkillTag;
use App\Features\Shared\Infra\Persistence\Cursor;
use App\Features\Shared\Infra\Persistence\PublicCursorCodec;
use App\Features\Shared\Infra\Services\PublicPageCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * `GET /experience` and `GET /{locale}/experience` — the public career
 * timeline: published items ordered `started_at DESC, id DESC` with
 * infinite scroll via the opaque composite cursor (the shared
 * {@see PublicCursorCodec} — the client treats it as opaque, only the
 * server encodes/decodes). `next_cursor` is null when the final batch is
 * incomplete — the exhaustion signal. SEO props carry the title and
 * description (ogImage falls back client-side to
 * `/images/og-default.png`); the canonical URL is always the base path.
 * Tech tags are the deduplicated union of the item's own and the linked
 * project's tags, assembled here from the entity's {@see TechSkillTag}s.
 */
final class PublicExperienceController
{
    public function __construct(
        private readonly GetPublicTimelineUseCase $timelineCase,
        private readonly PublicCursorCodec $cursorCodec,
        private readonly MarkdownContentPipelineInterface $pipeline,
        private readonly PublicPageCache $cache,
    ) {
    }

    public function index(ResponseFactory $inertia, Request $request): Response
    {
        $locale = App::getLocale();
        $cursor = $this->cursorCodec->decode((string) $request->query('cursor', ''));

        $props = $request->query() === []
            ? $this->cache->remember(
                'experience-index:'.$locale.':'.$request->path(),
                fn (): array => $this->indexProps($locale),
            )
            : $this->indexProps($locale, $cursor?->id, $cursor === null ? null : (string) $cursor->sortValue);

        return $inertia->render('Experience/Public/Pages/ExperienceTimelinePage', $props);
    }

    /**
     * @return array<string, mixed>
     */
    private function indexProps(string $locale, ?int $cursorId = null, ?string $cursorStartedAt = null): array
    {
        $data = $this->timelineCase->get(
            cursorId: $cursorId,
            cursorStartedAt: $cursorStartedAt,
            locale: $locale,
        );

        /** @var TimelineItem[] $items */
        $items = $data['items'];
        /** @var array<int, array{name: string, slug: string, tech_tags: TechSkillTag[]}> $projectRefs */
        $projectRefs = $data['project_refs'];
        /** @var array{id: int, started_at: string}|null $nextCursor */
        $nextCursor = $data['next_cursor'];

        return [
            'items' => $this->cardProps($items, $projectRefs, $locale),
            'has_more' => $nextCursor !== null,
            'next_cursor' => $nextCursor === null
                ? null
                : $this->cursorCodec->encode(new Cursor(
                    id: $nextCursor['id'],
                    sortValue: $nextCursor['started_at'],
                )),
            'has_projects' => $data['has_projects'],
            'seo' => $this->seoProps((string) ($data['about_name'] ?? '')),
        ];
    }

    /**
     * @param  TimelineItem[]  $items
     * @param  array<int, array{name: string, slug: string, tech_tags: TechSkillTag[]}>  $projectRefs
     * @return array<int, array<string, mixed>>
     */
    private function cardProps(array $items, array $projectRefs, string $locale): array
    {
        return array_map(
            function (TimelineItem $item) use ($projectRefs, $locale): array {
                $period = $item->toPeriod();
                $related = $item->toRelatedProject();
                $projectName = null;
                $projectSlug = null;
                $projectUrl = null;

                if ($related !== null) {
                    $ownProjectId = $related->toOwnProjectId();

                    if ($ownProjectId !== null) {
                        $projectName = $projectRefs[$ownProjectId]['name'] ?? null;
                        $projectSlug = $projectRefs[$ownProjectId]['slug'] ?? null;
                    } else {
                        $projectName = $related->toExternalProjectName();
                        $projectUrl = $related->toExternalProjectUrl();
                    }
                }

                return [
                    'id' => $item->toId(),
                    'position' => $item->positionFor($locale),
                    'company_name' => $item->companyNameFor($locale),
                    'company_country' => $item->companyCountryFor($locale),
                    'project_id' => $related?->toOwnProjectId(),
                    'project_name' => $projectName,
                    'project_slug' => $projectSlug,
                    'project_url' => $projectUrl,
                    'short_description' => $this->pipeline->render($item->shortDescriptionFor($locale)),
                    'started_at' => $period->toStartedAt()->format('Y-m-d'),
                    'ended_at' => $period->toEndedAt()?->format('Y-m-d'),
                    'is_current' => $item->toIsCurrent(),
                    'tech_tags' => $this->techTagProps($item, $projectRefs),
                    'achievements' => $this->achievementProps($item->achievementsFor($locale)),
                ];
            },
            $items,
        );
    }

    /**
     * @param  array<int, array{description: string}>  $achievements
     * @return array<int, array{description: string}>
     */
    private function achievementProps(array $achievements): array
    {
        return array_map(
            fn (array $achievement): array => [
                'description' => $this->pipeline->renderInline($achievement['description']),
            ],
            $achievements,
        );
    }

    /**
     * The deduplicated union of the item's own tech tags and the linked
     * project's tags — own tags first, then the project's tags not already
     * present, both in the repository's display order (`skills.sort_order`,
     * then id).
     *
     * @param  array<int, array{name: string, slug: string, tech_tags: TechSkillTag[]}>  $projectRefs
     * @return array<int, array<string, mixed>>
     */
    private function techTagProps(TimelineItem $item, array $projectRefs): array
    {
        $tags = $item->toTechTags();

        $projectId = $item->toRelatedProject()?->toOwnProjectId();
        if ($projectId !== null) {
            $tags = [...$tags, ...($projectRefs[$projectId]['tech_tags'] ?? [])];
        }

        $props = [];
        $seen = [];
        foreach ($tags as $tag) {
            if (isset($seen[$tag->toId()])) {
                continue;
            }

            $seen[$tag->toId()] = true;
            $props[] = [
                'id' => $tag->toId(),
                'name' => $tag->toSkillName(),
                'icon' => $tag->toIcon(),
            ];
        }

        return $props;
    }

    /**
     * @return array<string, mixed>
     */
    private function seoProps(string $aboutName): array
    {
        return [
            'title' => __('seo.title_experience', ['name' => $aboutName]),
            'description' => __('seo.description_experience'),
            'canonical_url' => URL::to('/experience'),
            'og_url' => URL::to('/experience'),
            'og_type' => 'website',
        ];
    }
}

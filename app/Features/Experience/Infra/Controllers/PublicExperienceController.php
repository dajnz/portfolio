<?php

declare(strict_types=1);

namespace App\Features\Experience\Infra\Controllers;

use App\Features\Experience\App\UseCases\GetPublicTimelineUseCase;
use App\Features\Experience\Domain\Entities\TimelineItem;
use App\Features\Shared\Domain\Entities\TechSkillTag;
use App\Features\Shared\Infra\Persistence\Cursor;
use App\Features\Shared\Infra\Persistence\PublicCursorCodec;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * `GET /experience` and `GET /{locale}/experience` (routes.md:24,52) —
 * the public career timeline (public-section-ui.md:103-141): published
 * items ordered `started_at DESC, id DESC` with infinite scroll via the
 * opaque composite cursor (the shared {@see PublicCursorCodec} — the
 * client treats it as opaque, only the server encodes/decodes;
 * public-section-ui.md:316-327). `next_cursor` is null when the final
 * batch is incomplete — the exhaustion signal. SEO props per seo.md:43
 * (title/description; ogImage falls back client-side to
 * `/images/og-default.png`); the canonical URL is always the base path
 * (seo.md:315). Tech tags are the deduplicated union of the item's own
 * and the linked project's tags (content-management.md:174), assembled
 * here from the entity's {@see TechSkillTag}s.
 */
final class PublicExperienceController
{
    public function __construct(
        private readonly GetPublicTimelineUseCase $timelineCase,
        private readonly PublicCursorCodec $cursorCodec,
    ) {
    }

    public function index(ResponseFactory $inertia, Request $request): Response
    {
        $locale = app()->getLocale();
        $cursor = $this->cursorCodec->decode((string) $request->query('cursor', ''));

        $data = $this->timelineCase->get(
            cursorId: $cursor?->id,
            cursorStartedAt: $cursor === null ? null : (string) $cursor->sortValue,
            locale: $locale,
        );

        /** @var TimelineItem[] $items */
        $items = $data['items'];
        /** @var array<int, array{name: string, slug: string, tech_tags: TechSkillTag[]}> $projectRefs */
        $projectRefs = $data['project_refs'];
        /** @var array{id: int, started_at: string}|null $nextCursor */
        $nextCursor = $data['next_cursor'];

        return $inertia->render('Experience/Public/Pages/ExperienceTimelinePage', [
            'items' => $this->cardProps($items, $projectRefs, $locale),
            'has_more' => $nextCursor !== null,
            'next_cursor' => $nextCursor === null
                ? null
                : $this->cursorCodec->encode(new Cursor(
                    id: $nextCursor['id'],
                    sortValue: $nextCursor['started_at'],
                )),
            'seo' => $this->seoProps($locale),
        ]);
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

                if ($related !== null) {
                    $ownProjectId = $related->toOwnProjectId();

                    if ($ownProjectId !== null) {
                        $projectName = $projectRefs[$ownProjectId]['name'] ?? null;
                        $projectSlug = $projectRefs[$ownProjectId]['slug'] ?? null;
                    } else {
                        $projectName = $related->toExternalProjectName();
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
                    'short_description' => $item->shortDescriptionFor($locale),
                    'started_at' => $period->toStartedAt()->format('Y-m-d'),
                    'ended_at' => $period->toEndedAt()?->format('Y-m-d'),
                    'is_current' => $item->toIsCurrent(),
                    'tech_tags' => $this->techTagProps($item, $projectRefs),
                    'achievements' => $item->achievementsFor($locale),
                ];
            },
            $items,
        );
    }

    /**
     * The deduplicated union of the item's own tech tags and the linked
     * project's tags (content-management.md:174) — own tags first, then
     * the project's tags not already present, both in the repository's
     * display order (`skills.sort_order`, then id).
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
    private function seoProps(string $locale): array
    {
        $name = (string) DB::table('about_info as a')
            ->leftJoin('about_info_translations as at', function ($join) use ($locale): void {
                $join->on('at.about_info_id', '=', 'a.id')->where('at.locale', '=', $locale);
            })
            ->leftJoin('about_info_translations as en', function ($join): void {
                $join->on('en.about_info_id', '=', 'a.id')->where('en.locale', '=', 'en');
            })
            ->where('a.id', 1)
            ->value(DB::raw('coalesce(at.name, en.name)'));

        return [
            'title' => __('seo.title_experience', ['name' => $name]),
            'description' => __('seo.description_experience'),
            'canonical_url' => url('/experience'),
            'og_type' => 'website',
        ];
    }
}

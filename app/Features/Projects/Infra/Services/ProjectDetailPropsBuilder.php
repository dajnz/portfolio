<?php

declare(strict_types=1);

namespace App\Features\Projects\Infra\Services;

use App\Features\Projects\App\DTOs\ProjectDataDto;
use App\Features\Projects\App\DTOs\ProjectSeoDto;
use App\Features\Projects\App\DTOs\TechWriteupLinkDto;

/**
 * Prepares the wire props of the public project detail page
 * (`ProjectDetailData` + `SeoProps` — projects.yaml, shared.yaml) from
 * the app-layer data DTOs: the per-locale maps are resolved for the
 * request locale (with the `en` fallback) and the nested media rows
 * are mapped into the documented shapes. Shared by the public detail
 * and the signed preview controllers.
 */
final class ProjectDetailPropsBuilder
{
    public function detail(
        ProjectDataDto $project,
        ?TechWriteupLinkDto $techWriteup,
        string $locale,
    ): array {
        $translations = $project->translations;

        return [
            'id' => $project->id,
            'title' => self::localeText($translations->titles, $locale),
            'slug' => $project->slug,
            'full_description' => self::localeNullableText($translations->fullDescriptions, $locale),
            'problem' => self::localeNullableText($translations->problems, $locale),
            'solution' => self::localeNullableText($translations->solutions, $locale),
            'brief_description' => self::localeText($translations->briefDescriptions, $locale),
            'published_at' => $project->publishedAt?->format('c'),
            'preview_image' => $project->preview?->url,
            'preview_image_alt' => $project->preview?->altLabels ?? [],
            'github_url' => $project->links->sourceCodeUrl,
            'live_demo_url' => $project->links->liveDemoUrl,
            'is_featured' => $project->isFeatured,
            'tech_tags' => $project->techTags,
            'screenshots' => array_map(
                static fn ($screenshot): array => [
                    'id' => $screenshot->id,
                    'image_path' => $screenshot->image->url,
                    'alt_text' => $screenshot->image->altLabels,
                    'title' => $screenshot->titles,
                    'description' => $screenshot->image->descriptions,
                    'sort_order' => $screenshot->sortOrder,
                ],
                $project->screenshots,
            ),
            'detail_items' => array_map(
                static fn ($item): array => [
                    'id' => $item->id,
                    'icon' => $item->icon,
                    'title' => self::localeText($item->titles, $locale),
                    'description' => self::localeText($item->descriptions, $locale),
                ],
                $project->engineeringDetails,
            ),
            'tech_writeup' => $techWriteup === null
                ? null
                : [
                    'id' => $techWriteup->id,
                    'slug' => $techWriteup->slug,
                    'title' => $techWriteup->title,
                ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function seo(ProjectSeoDto $seo): array
    {
        return [
            'title' => $seo->title,
            'description' => $seo->description,
            'canonical_url' => $seo->canonicalUrl,
            'og_type' => $seo->ogType,
            'json_ld' => $seo->jsonLd,
            'robots' => $seo->robots,
        ];
    }

    /**
     * @param  array<string, string|null>  $map
     */
    private static function localeText(array $map, string $locale): string
    {
        $value = $map[$locale] ?? $map['en'] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * @param  array<string, string|null>  $map
     */
    private static function localeNullableText(array $map, string $locale): ?string
    {
        $value = $map[$locale] ?? $map['en'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}

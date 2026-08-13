<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Controllers;

use App\Features\Blog\App\DTOs\RelatedArticleDto;
use App\Features\Blog\App\DTOs\TagOptionDto;
use App\Features\Blog\App\UseCases\GetArticlePreviewUseCase;
use App\Features\Blog\Domain\Exceptions\ArticleNotFoundException;
use Illuminate\Http\Request;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * The signed preview route `GET /admin/preview/blog/{article}`
 * (routes.md:155, content-management.md:245): excluded from the auth
 * middleware — access is via a signed temporary URL (1-hour expiry,
 * `URL::temporarySignedRoute`), verified here. Renders the PUBLIC
 * article page, bypassing the `is_published` scope.
 *
 * Step 8.1 serves the raw stored data (Markdown content, no reactions
 * — the shared content pipeline, SEO props and reaction counts land
 * with the public surface in step 8.2).
 */
final class PreviewController
{
    public function __construct(
        private readonly GetArticlePreviewUseCase $previewCase,
    ) {
    }

    public function article(ResponseFactory $inertia, Request $request, int $article): Response
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        try {
            $data = $this->previewCase->get($article, app()->getLocale());
        } catch (ArticleNotFoundException) {
            abort(404);
        }

        $locale = app()->getLocale();

        return $inertia->render('Blog/Public/Pages/BlogArticlePage', [
            'article' => [
                'id' => $data->id,
                'title' => $this->localeValue($data->translations->titles, $locale),
                'slug' => $data->slug,
                'content' => $this->localeValue($data->translations->contents, $locale),
                'short_description' => $this->localeValue($data->translations->shortDescriptions, $locale),
                'published_at' => $data->publishedAt?->format('c'),
                'reading_time_minutes' => $data->readingTimeMinutes,
                'lead_image' => $data->leadImage?->url,
                'lead_image_alt' => $data->leadImage?->altLabels ?? [],
                'tags' => array_map(
                    static fn (TagOptionDto $tag): array => self::tagDataProps($tag),
                    $data->tags,
                ),
                'related' => array_map(
                    static fn (RelatedArticleDto $related): array => self::relatedProps($related),
                    $data->related,
                ),
            ],
            'reactions' => [
                'user_reaction' => null,
                'likes' => 0,
                'dislikes' => 0,
            ],
            'seo' => [
                'title' => $this->localeValue($data->translations->titles, $locale),
                'description' => $this->localeValue($data->translations->shortDescriptions, $locale),
                'robots' => 'noindex, nofollow',
            ],
        ]);
    }

    /**
     * The `TagData` wire shape of a tag — `{id, slug, name}` with the
     * locale-resolved name.
     *
     * @return array<string, mixed>
     */
    private static function tagDataProps(TagOptionDto $tag): array
    {
        return [
            'id' => $tag->id,
            'slug' => $tag->slug,
            'name' => $tag->name,
        ];
    }

    /**
     * The related-article row — `{id, title}`.
     *
     * @return array<string, mixed>
     */
    private static function relatedProps(RelatedArticleDto $related): array
    {
        return [
            'id' => $related->id,
            'title' => $related->title,
        ];
    }

    /**
     * @param  array<string, mixed>  $map
     */
    private function localeValue(array $map, string $locale): string
    {
        $value = $map[$locale] ?? $map['en'] ?? '';

        return is_string($value) ? $value : '';
    }
}

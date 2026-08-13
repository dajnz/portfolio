<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Controllers;

use App\Features\Blog\App\DTOs\ArticleCardDto;
use App\Features\Blog\App\DTOs\PublicArticleDto;
use App\Features\Blog\App\DTOs\TagOptionDto;
use App\Features\Blog\App\UseCases\GetPublicArticleUseCase;
use App\Features\Blog\Domain\Exceptions\ArticleNotFoundException;
use Illuminate\Http\Request;
use Inertia\Response;
use Inertia\ResponseFactory;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The signed preview route `GET /admin/preview/blog/{article}`
 * (routes.md:155, content-management.md:245): excluded from the auth
 * middleware — access is via a signed temporary URL (1-hour expiry,
 * `URL::temporarySignedRoute`), verified here. Renders the PUBLIC
 * article page, bypassing the `is_published` scope: the shared
 * content pipeline HTML, the SEO props with the `noindex` directive
 * and a null reactions widget (B211 — step 8.2 replaced the raw-data
 * preview with the full public surface).
 */
final class PreviewController
{
    public function __construct(
        private readonly GetPublicArticleUseCase $previewCase,
    ) {
    }

    public function article(ResponseFactory $inertia, Request $request, int $article): Response
    {
        if (! $request->hasValidSignature()) {
            throw new AccessDeniedHttpException();
        }

        try {
            $detail = $this->previewCase->getById($article, App::getLocale());
        } catch (ArticleNotFoundException) {
            throw new NotFoundHttpException();
        }

        return $inertia->render('Blog/Public/Pages/BlogArticlePage', [
            'article' => self::detailProps($detail),
            'reactions' => null,
            'seo' => self::seoProps($detail),
        ]);
    }

    /**
     * The `ArticleDetailData` wire shape of the article page.
     *
     * @return array<string, mixed>
     */
    private static function detailProps(PublicArticleDto $detail): array
    {
        return [
            'id' => $detail->id,
            'title' => $detail->title,
            'slug' => $detail->slug,
            'content_html' => $detail->contentHtml,
            'short_description' => $detail->shortDescription,
            'published_at' => $detail->publishedAt?->format('c'),
            'reading_time_minutes' => $detail->readingTimeMinutes,
            'lead_image' => $detail->leadImage?->url,
            'lead_image_alt' => $detail->leadImage?->altLabelsMap() ?? [],
            'tags' => array_map(
                static fn (TagOptionDto $tag): array => [
                    'id' => $tag->id,
                    'slug' => $tag->slug,
                    'name' => $tag->name,
                ],
                $detail->tags,
            ),
            'related' => array_map(
                static fn (ArticleCardDto $card): array => [
                    'id' => $card->id,
                    'title' => $card->title,
                    'slug' => $card->slug,
                    'short_description' => $card->shortDescription,
                    'published_at' => $card->publishedAt?->format('c'),
                    'reading_time_minutes' => $card->readingTimeMinutes,
                    'lead_image' => $card->leadImage,
                    'tags' => array_map(
                        static fn (TagOptionDto $tag): array => [
                            'id' => $tag->id,
                            'slug' => $tag->slug,
                            'name' => $tag->name,
                        ],
                        $card->tags,
                    ),
                ],
                $detail->related,
            ),
            'views_count' => $detail->views,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function seoProps(PublicArticleDto $detail): array
    {
        $seo = $detail->seo;

        return [
            'title' => $seo->title,
            'description' => $seo->description,
            'canonical_url' => $seo->canonicalUrl,
            'og_type' => $seo->ogType,
            'og_image' => $seo->ogImage,
            'json_ld' => $seo->jsonLd,
            'robots' => 'noindex, nofollow',
        ];
    }
}

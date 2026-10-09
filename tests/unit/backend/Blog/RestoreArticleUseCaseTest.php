<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Blog;

use App\Features\Blog\App\UseCases\RestoreArticleUseCase;
use App\Features\Blog\Domain\Exceptions\ArticleSlugOccupiedException;
use Tests\Support\Fakes\FakeArticleRepository;
use Tests\Support\Fakes\FakeCountCacheInvalidator;
use Tests\Support\Fakes\FakeSitemapInvalidator;

/**
 * The restore slug-uniqueness re-check (ADR-0006, content-management.md:901)
 * — single and bulk, with the batched reads (one slugs read + one
 * occupied-subset read). The conflict state cannot arise through the
 * HTTP surface (the DB unique index covers trashed rows — soft-deleted
 * entities occupy their slug — so an active article can never hold a
 * trashed article's slug), hence the repository fake. The sitemap
 * invalidation decision (a restored published article re-enters the
 * sitemap) lives in this use case.
 */
describe('RestoreArticleUseCase', function (): void {
    it('rejects a single restore when the slug is occupied by an active article', function (): void {
        $repository = new FakeArticleRepository(
            trashedSlugs: [7 => 'taken-slug'],
            activeSlugs: ['taken-slug'],
        );
        $useCase = new RestoreArticleUseCase(
            $repository,
            new FakeCountCacheInvalidator(),
            new FakeSitemapInvalidator(),
        );

        expect(fn () => $useCase->restore(7))
            ->toThrow(ArticleSlugOccupiedException::class, 'taken-slug')
            ->and($repository->restoredIds)->toBe([]);
    });

    it('restores a single article when the slug is free and invalidates the count cache', function (): void {
        $repository = new FakeArticleRepository(
            trashedSlugs: [7 => 'free-slug'],
        );
        $countCache = new FakeCountCacheInvalidator();
        $useCase = new RestoreArticleUseCase(
            $repository,
            $countCache,
            new FakeSitemapInvalidator(),
        );

        $useCase->restore(7);

        expect($repository->restoredIds)->toBe([7])
            ->and($countCache->invalidated)->toBe(['articles']);
    });

    it('invalidates the sitemap only when a published article is restored', function (): void {
        $sitemap = new FakeSitemapInvalidator();
        $repository = new FakeArticleRepository(
            trashedSlugs: [7 => 'free-slug'],
            publishedIds: [7],
        );
        $useCase = new RestoreArticleUseCase(
            $repository,
            new FakeCountCacheInvalidator(),
            $sitemap,
        );

        $useCase->restore(7);

        expect($sitemap->invalidations)->toBe(1);
    });

    it('rejects a bulk restore when any of the slugs is occupied', function (): void {
        $repository = new FakeArticleRepository(
            trashedSlugs: [7 => 'free-slug', 9 => 'taken-slug'],
            activeSlugs: ['taken-slug'],
        );
        $useCase = new RestoreArticleUseCase(
            $repository,
            new FakeCountCacheInvalidator(),
            new FakeSitemapInvalidator(),
        );

        expect(fn () => $useCase->restoreMany([7, 9]))
            ->toThrow(ArticleSlugOccupiedException::class, 'taken-slug')
            ->and($repository->restoredManyIds)->toBe([]);
    });

    it('restores the whole batch when every slug is free', function (): void {
        $repository = new FakeArticleRepository(
            trashedSlugs: [7 => 'free-slug', 9 => 'other-slug'],
        );
        $countCache = new FakeCountCacheInvalidator();
        $useCase = new RestoreArticleUseCase(
            $repository,
            $countCache,
            new FakeSitemapInvalidator(),
        );

        $useCase->restoreMany([7, 9]);

        expect($repository->restoredManyIds)->toBe([7, 9])
            ->and($countCache->invalidated)->toBe(['articles']);
    });
});

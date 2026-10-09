<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Blog;

use App\Features\Blog\App\DTOs\TagDraftDto;
use App\Features\Blog\App\Services\TagSlugService;
use App\Features\Blog\App\UseCases\StoreTagUseCase;
use App\Features\Blog\Domain\Exceptions\DuplicateArticleTagNameException;
use App\Features\Blog\Domain\Entities\ArticleTag;
use Tests\Support\Fakes\FakeArticleTagRepository;
use Tests\Support\Fakes\FakeCountCacheInvalidator;

describe('StoreTagUseCase', function (): void {
    it('rejects a tag whose name matches an existing skill (case-insensitive)', function (): void {
        $repository = new FakeArticleTagRepository(skillNames: ['PHP']);
        $useCase = new StoreTagUseCase(
            $repository,
            new TagSlugService($repository),
            new FakeCountCacheInvalidator(),
        );

        expect(fn () => $useCase->store(new TagDraftDto(
            submittedSlug: 'php',
            names: ['en' => 'php', 'ru' => 'PHP'],
        )))->toThrow(DuplicateArticleTagNameException::class);
    });

    it('checks every submitted locale name, not only the EN one', function (): void {
        $repository = new FakeArticleTagRepository(skillNames: ['Ларавел']);
        $useCase = new StoreTagUseCase(
            $repository,
            new TagSlugService($repository),
            new FakeCountCacheInvalidator(),
        );

        expect(fn () => $useCase->store(new TagDraftDto(
            submittedSlug: 'laravel',
            names: ['en' => 'Laravel', 'ru' => 'Ларавел'],
        )))->toThrow(DuplicateArticleTagNameException::class);
    });

    it('stores the tag when no skill matches and invalidates the tags count cache', function (): void {
        $repository = new FakeArticleTagRepository(skillNames: ['PHP']);
        $countCache = new FakeCountCacheInvalidator();
        $useCase = new StoreTagUseCase(
            $repository,
            new TagSlugService($repository),
            $countCache,
        );

        $tag = $useCase->store(new TagDraftDto(
            submittedSlug: 'laravel',
            names: ['en' => 'Laravel', 'ru' => 'Ларавел'],
        ));

        expect($repository->savedTags)->toHaveCount(1)
            ->and($tag)->toBeInstanceOf(ArticleTag::class)
            ->and($tag->toSlug())->toBe('laravel')
            ->and($tag->toTranslations())->toHaveCount(2)
            ->and($countCache->invalidated)->toBe(['tags']);
    });

    it('keeps a manual slug override as-is (ADR-0006)', function (): void {
        $repository = new FakeArticleTagRepository(skillNames: []);
        $useCase = new StoreTagUseCase(
            $repository,
            new TagSlugService($repository),
            new FakeCountCacheInvalidator(),
        );

        $tag = $useCase->store(new TagDraftDto(
            submittedSlug: 'my-manual-slug',
            names: ['en' => 'Laravel'],
        ));

        expect($tag->toSlug())->toBe('my-manual-slug');
    });

    it('appends a numeric collision suffix to the auto-generated slug (ADR-0006)', function (): void {
        $repository = new FakeArticleTagRepository(skillNames: [], taken: ['laravel', 'laravel-2']);
        $useCase = new StoreTagUseCase(
            $repository,
            new TagSlugService($repository),
            new FakeCountCacheInvalidator(),
        );

        $tag = $useCase->store(new TagDraftDto(
            submittedSlug: 'laravel',
            names: ['en' => 'Laravel'],
        ));

        expect($tag->toSlug())->toBe('laravel-3');
    });
});

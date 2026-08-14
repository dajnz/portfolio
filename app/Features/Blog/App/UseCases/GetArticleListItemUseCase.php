<?php

declare(strict_types=1);

namespace App\Features\Blog\App\UseCases;

use App\Features\Blog\App\DTOs\ArticleListItemDto;
use App\Features\Blog\App\DTOs\ArticleTagOptionDto;
use App\Features\Blog\App\DTOs\TagOptionDto;
use App\Features\Blog\Domain\Exceptions\ArticleNotFoundException;
use App\Features\Blog\Infra\Repositories\ArticleListQueryRepositoryInterface;

/**
 * The single admin list item after a quick action (publish/unpublish,
 * related attach/detach, skill attach/detach — content-management.md:
 * 255): the mutations run through their own use cases, this one
 * re-reads the row so the list updates without a reload.
 */
final class GetArticleListItemUseCase
{
    public function __construct(
        private readonly ArticleListQueryRepositoryInterface $repository,
    ) {
    }

    public function get(int $id): ArticleListItemDto
    {
        $row = $this->repository->findAdminRow($id);

        if ($row === null) {
            throw ArticleNotFoundException::forId($id);
        }

        $tags = [];
        foreach ($this->repository->articleTagsFor([$id])[$id] ?? [] as $tag) {
            $tags[] = ArticleTagOptionDto::forArticleTag(
                new TagOptionDto(id: (int) $tag->id, slug: $tag->slug, name: $tag->name),
            );
        }

        foreach ($this->repository->skillTagsFor([$id])[$id] ?? [] as $skill) {
            $tags[] = ArticleTagOptionDto::forSkillTag($skill);
        }

        return ArticleListItemDto::fromRow(
            $row,
            tags: $tags,
            relatedCount: $this->repository->relatedCountFor([$id])[$id] ?? 0,
        );
    }
}

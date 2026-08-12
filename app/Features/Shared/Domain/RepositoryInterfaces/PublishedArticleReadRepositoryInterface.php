<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\RepositoryInterfaces;

/**
 * Read-only access to published blog articles for cross-feature
 * consumers: the tech-writeup link of the public project detail
 * (content-management.md:133 — the link renders only while the linked
 * article is published; the stored reference is preserved so the link
 * reappears on re-publish). The write side and the full read side
 * belong to the Blog feature (8.x).
 */
interface PublishedArticleReadRepositoryInterface
{
    /**
     * The published, non-trashed article with the given id — its slug
     * and the `en`-fallback title (the wire `tech_writeup`
     * `{id, slug, title}` shape). Null when absent or unpublished.
     *
     * @return array{id: int, slug: string, title: string}|null
     */
    public function findPublishedById(int $articleId): ?array;
}

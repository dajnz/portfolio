<?php

declare(strict_types=1);

namespace App\Features\Blog\Domain\RepositoryInterfaces;

use App\Features\Blog\Domain\Entities\ArticleReaction;

/**
 * The reaction persistence (database.md:406-420,
 * content-management.md:207-212): one reaction per
 * `(article_id, fingerprint)` pair, upsert semantics — a different
 * reaction type updates the row, the same type deletes it (undo).
 * Consumed by the public reactions endpoint (step 8.2); the
 * fingerprint is server-generated (keyed HMAC of ip|user_agent, B4).
 */
interface ArticleReactionRepositoryInterface
{
    public function findByArticleAndFingerprint(int $articleId, string $fingerprint): ?ArticleReaction;

    /**
     * Inserts the reaction; an existing `(article_id, fingerprint)`
     * pair is updated (the type change) — the same-reaction undo is
     * the use case's decision (it deletes the row).
     */
    public function save(ArticleReaction $reaction): void;

    public function delete(int $articleId, string $fingerprint): void;

    /**
     * The like/dislike totals of the article.
     *
     * @return array{likes: int, dislikes: int}
     */
    public function countsFor(int $articleId): array;
}

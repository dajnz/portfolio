<?php

declare(strict_types=1);

namespace App\Features\Blog\App\UseCases;

use App\Features\Blog\App\DTOs\ReactionOutcomeDto;
use App\Features\Blog\Domain\Entities\ArticleReaction;
use App\Features\Blog\Domain\Exceptions\ArticleNotFoundException;
use App\Features\Blog\Domain\RepositoryInterfaces\ArticleReactionRepositoryInterface;
use App\Features\Blog\Domain\RepositoryInterfaces\ArticleRepositoryInterface;
use App\Features\Blog\Domain\ValueObjects\ArticleId;
use App\Features\Blog\Domain\ValueObjects\ReactionType;
use DateTimeImmutable;

/**
 * The reactions endpoint (routes.md:190, content-management.md:
 * 207-212): one reaction per server-generated fingerprint per article
 * (keyed HMAC of ip|user_agent — B4; the client can never spoof a
 * different identity). Toggling: a first reaction inserts, the same
 * reaction again undoes (deletes the row), the opposite reaction
 * switches the type. The totals are reported after the mutation.
 * Unpublished/trashed articles answer 404 (the public endpoint only).
 */
final class ToggleArticleReactionUseCase
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articles,
        private readonly ArticleReactionRepositoryInterface $reactions,
    ) {
    }

    public function toggle(int $articleId, ReactionType $reaction, string $fingerprint): ReactionOutcomeDto
    {
        $article = $this->articles->findById($articleId);

        if ($article === null || ! $article->toIsPublished()) {
            throw ArticleNotFoundException::forId($articleId);
        }

        $existing = $this->reactions->findByArticleAndFingerprint($articleId, $fingerprint);

        if ($existing === null) {
            $this->reactions->save($this->newReaction($articleId, $reaction, $fingerprint));
            $current = $reaction;
        } elseif ($existing->toReaction() === $reaction) {
            $this->reactions->delete($articleId, $fingerprint);
            $current = null;
        } else {
            $this->reactions->save($this->newReaction($articleId, $reaction, $fingerprint));
            $current = $reaction;
        }

        $counts = $this->reactions->countsFor($articleId);

        return new ReactionOutcomeDto(
            reaction: $current,
            likes: $counts['likes'],
            dislikes: $counts['dislikes'],
        );
    }

    private function newReaction(int $articleId, ReactionType $reaction, string $fingerprint): ArticleReaction
    {
        return new ArticleReaction(
            articleId: new ArticleId($articleId),
            fingerprint: $fingerprint,
            reaction: $reaction,
            createdAt: new DateTimeImmutable(),
        );
    }
}

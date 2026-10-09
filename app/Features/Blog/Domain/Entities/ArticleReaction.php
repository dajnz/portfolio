<?php

declare(strict_types=1);

namespace App\Features\Blog\Domain\Entities;

use App\Features\Blog\Domain\ValueObjects\ArticleId;
use App\Features\Blog\Domain\ValueObjects\ReactionType;
use App\Features\Shared\Domain\BaseEntity;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A visitor reaction to an article (database.md:406-420): the
 * server-generated keyed-HMAC fingerprint (`hash_hmac('sha256',
 * ip.'|'.user_agent, key)` — content-management.md:212, ADR-0017), the
 * reaction type (`like`/`dislike` CHECK) and the first-reaction
 * timestamp (upsert semantics — `created_at` only, no `updated_at`).
 * One reaction per `(article_id, fingerprint)` pair.
 */
final class ArticleReaction extends BaseEntity
{
    public function __construct(
        private ArticleId $articleId,
        private string $fingerprint,
        private ReactionType $reaction,
        private DateTimeImmutable $createdAt,
        private int $id = 0,
    ) {
        if ($fingerprint === '') {
            throw new InvalidArgumentException('Reaction fingerprint must not be empty.');
        }
    }

    public function toId(): int
    {
        return $this->id;
    }

    public function toArticleId(): int
    {
        return $this->articleId->toValue();
    }

    public function toFingerprint(): string
    {
        return $this->fingerprint;
    }

    public function toReaction(): ReactionType
    {
        return $this->reaction;
    }

    public function toCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}

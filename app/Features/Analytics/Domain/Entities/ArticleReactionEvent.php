<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\Entities;

use App\Features\Analytics\Domain\Enums\SocialReaction;
use App\Features\Analytics\Domain\ValueObjects\ClientIds;
use App\Features\Analytics\Domain\ValueObjects\PageDetails;
use DateTimeImmutable;
use InvalidArgumentException;

final class ArticleReactionEvent extends AnalyticsEvent
{
    public const EVENT_TYPE = 'article_reaction';

    public function __construct(
        int $id,
        ClientIds $clientIds,
        PageDetails $pageDetails,
        private readonly int $articleId,
        private readonly SocialReaction $reaction,
        DateTimeImmutable $createdAt,
    ) {
        parent::__construct($id, $clientIds, $pageDetails, $createdAt);

        if ($this->articleId < 1) {
            throw new InvalidArgumentException('Article id must be a positive integer.');
        }
    }

    public function toArticleId(): int
    {
        return $this->articleId;
    }

    public function toReaction(): SocialReaction
    {
        return $this->reaction;
    }

    public function toEventSpecificData(): array
    {
        return [
            'article_id' => $this->articleId,
            'reaction' => $this->reaction->value,
        ];
    }
}

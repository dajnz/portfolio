<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\Entities;

use App\Features\Analytics\Domain\ValueObjects\ClientIds;
use App\Features\Analytics\Domain\ValueObjects\PageDetails;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The `article_read` event (analytics.md:25): the read-progress
 * trigger fired once per reader past the scroll threshold — the
 * article id and the completion percentage. The frontend sends no
 * seconds (ADR-0017): the per-article average read time is derived from
 * `time_on_page` events on the article page instead.
 */
final class ArticleReadEvent extends AnalyticsEvent
{
    public const EVENT_TYPE = 'article_read';

    public const MAX_PERCENTAGE = 100;

    public function __construct(
        int $id,
        ClientIds $clientIds,
        PageDetails $pageDetails,
        private readonly int $articleId,
        private readonly int $percentageCompleted,
        DateTimeImmutable $createdAt,
    ) {
        parent::__construct($id, $clientIds, $pageDetails, $createdAt);

        if ($this->articleId < 1) {
            throw new InvalidArgumentException('Article id must be a positive integer.');
        }

        if ($this->percentageCompleted < 0 || $this->percentageCompleted > self::MAX_PERCENTAGE) {
            throw new InvalidArgumentException(
                sprintf(
                    'Percentage completed must be between 0 and %d.',
                    self::MAX_PERCENTAGE,
                ),
            );
        }
    }

    public function toArticleId(): int
    {
        return $this->articleId;
    }

    public function toPercentageCompleted(): int
    {
        return $this->percentageCompleted;
    }

    public function toEventSpecificData(): array
    {
        return [
            'article_id' => $this->articleId,
            'percentage_completed' => $this->percentageCompleted,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Features\Blog\App\Services;

use App\Features\Blog\Domain\RepositoryInterfaces\ArticleReactionRepositoryInterface;
use App\Features\Settings\Domain\Enums\FeatureFlagName;
use App\Features\Settings\Domain\RepositoryInterfaces\FeatureFlagRepositoryInterface;

/**
 * Resolves the article reaction widget state: `null` when the
 * `social_reactions` feature flag is disabled or no client fingerprint was
 * supplied, otherwise the visitor's reaction and the like/dislike counts.
 */
final class ArticleReactionState
{
    public function __construct(
        private readonly ArticleReactionRepositoryInterface $reactions,
        private readonly FeatureFlagRepositoryInterface $flags,
    ) {
    }

    /**
     * @return array{user_reaction: string|null, likes: int, dislikes: int}|null
     */
    public function forArticle(int $articleId, ?string $fingerprint): ?array
    {
        if ($fingerprint === null || ! $this->flags->isEnabled(FeatureFlagName::SocialReactions)) {
            return null;
        }

        $existing = $this->reactions->findByArticleAndFingerprint($articleId, $fingerprint);
        $counts = $this->reactions->countsFor($articleId);

        return [
            'user_reaction' => $existing?->toReaction()->value,
            'likes' => $counts['likes'],
            'dislikes' => $counts['dislikes'],
        ];
    }
}

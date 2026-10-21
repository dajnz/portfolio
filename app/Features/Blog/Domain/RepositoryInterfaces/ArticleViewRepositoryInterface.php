<?php

declare(strict_types=1);

namespace App\Features\Blog\Domain\RepositoryInterfaces;

interface ArticleViewRepositoryInterface
{
    /**
     * Atomically increments the article's view counter, inserting the
     * counter row on the first view.
     */
    public function increment(int $articleId): void;
}

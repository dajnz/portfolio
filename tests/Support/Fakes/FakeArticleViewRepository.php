<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Features\Blog\Domain\RepositoryInterfaces\ArticleViewRepositoryInterface;

final class FakeArticleViewRepository implements ArticleViewRepositoryInterface
{
    /** @var list<int> */
    public array $incremented = [];

    public function increment(int $articleId): void
    {
        $this->incremented[] = $articleId;
    }
}

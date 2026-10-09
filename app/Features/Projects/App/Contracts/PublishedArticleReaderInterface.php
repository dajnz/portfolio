<?php

declare(strict_types=1);

namespace App\Features\Projects\App\Contracts;

use App\Features\Projects\App\DTOs\ArticleOptionDto;

/**
 * App-layer contract for the published-articles read of the project
 * form's "Tech Writeup Article" select. The cross-feature read over the
 * Blog tables lives in the Infra layer ({@see PublishedArticleReader}).
 */
interface PublishedArticleReaderInterface
{
    /**
     * @return ArticleOptionDto[]
     */
    public function findAll(string $locale): array;
}

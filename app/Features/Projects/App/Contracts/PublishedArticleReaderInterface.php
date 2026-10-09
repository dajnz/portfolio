<?php

declare(strict_types=1);

namespace App\Features\Projects\App\Contracts;

use App\Features\Projects\App\DTOs\ArticleOptionDto;

/**
 * The published-articles read for the project form's "Tech Writeup
 * Article" select (admin-section-ui.md:356). App-layer contract — the
 * cross-feature read over the Blog tables lives in the Infra layer
 * (PublishedArticleReader, ADR-0003).
 */
interface PublishedArticleReaderInterface
{
    /**
     * @return ArticleOptionDto[]
     */
    public function findAll(string $locale): array;
}

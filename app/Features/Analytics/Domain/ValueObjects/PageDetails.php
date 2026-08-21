<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\ValueObjects;

use App\Features\Analytics\Domain\Enums\PageType;
use InvalidArgumentException;

/**
 * The page context of an analytics event (B289): which public page the
 * event happened on (the type), which entity that page showed (the id
 * — only for the entity pages) and the page URL. The URL moved out of
 * `AnalyticsEvent` into this VO. The typed entity-id accessors
 * (`toBlogArticleId()`/`toProjectId()`) return null when the page type
 * does not match or no entity id was sent — the frontend supplies the
 * context, no backend URL lookup.
 */
final class PageDetails
{
    public const MAX_PAGE_URL_LENGTH = 500;

    public function __construct(
        private readonly ?PageType $pageType,
        private readonly ?int $pageEntityId,
        private readonly ?string $pageUrl,
    ) {
        if ($pageEntityId !== null && $pageEntityId < 1) {
            throw new InvalidArgumentException('Page entity id must be a positive integer.');
        }

        if ($pageUrl !== null && mb_strlen($pageUrl) > self::MAX_PAGE_URL_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('Page url must not exceed %d characters.', self::MAX_PAGE_URL_LENGTH),
            );
        }
    }

    public function toPageType(): ?PageType
    {
        return $this->pageType;
    }

    public function toPageEntityId(): ?int
    {
        return $this->pageEntityId;
    }

    public function toPageUrl(): ?string
    {
        return $this->pageUrl;
    }

    /** The blog article id — null unless the event happened on an article page. */
    public function toBlogArticleId(): ?int
    {
        return $this->pageType === PageType::BlogArticle
            ? $this->pageEntityId
            : null;
    }

    /** The project id — null unless the event happened on a project detail page. */
    public function toProjectId(): ?int
    {
        return $this->pageType === PageType::ProjectDetail
            ? $this->pageEntityId
            : null;
    }
}

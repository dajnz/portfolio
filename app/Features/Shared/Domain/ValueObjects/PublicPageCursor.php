<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Value object: a cursor position in a public infinite-scroll list
 * (public-section-ui.md:316-327) — the item id plus the active sort
 * value (`published_at`, `tech_tags_count` or the FTS rank as a
 * string). The opaque base64url wire form is the
 * {@see \App\Features\Shared\Infra\Persistence\PublicCursorCodec}'s
 * concern; domain and application code reason about this VO.
 * Immutable; both parts are mandatory.
 */
final readonly class PublicPageCursor
{
    public function __construct(
        private int $id,
        private string $sortValue,
    ) {
        if ($id < 1) {
            throw new InvalidArgumentException('Page cursor id must be a positive integer.');
        }

        if ($sortValue === '') {
            throw new InvalidArgumentException('Page cursor sort value must not be empty.');
        }
    }

    public function toId(): int
    {
        return $this->id;
    }

    public function toSortValue(): string
    {
        return $this->sortValue;
    }

    public function equals(self $other): bool
    {
        return $other->id === $this->id && $other->sortValue === $this->sortValue;
    }
}

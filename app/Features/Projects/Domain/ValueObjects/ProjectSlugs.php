<?php

declare(strict_types=1);

namespace App\Features\Projects\Domain\ValueObjects;

use App\Features\Shared\Domain\ValueObjects\Slug;
use InvalidArgumentException;

/**
 * Value object: a project's slug history — the current kebab-case slug
 * plus the previously recorded slugs (the `old_slugs` history,
 * content-management.md:454-464). Both are validated by
 * {@see Slug}. `reassign()` yields a new instance whose current slug is
 * swapped and the previous one appended to the history.
 */
final readonly class ProjectSlugs
{
    /**
     * @param  Slug[]  $oldSlugs
     */
    public function __construct(
        private Slug $currentSlug,
        private array $oldSlugs = [],
    ) {
        foreach ($oldSlugs as $oldSlug) {
            if (! $oldSlug instanceof Slug) {
                throw new InvalidArgumentException('Project old slugs must be Slug instances.');
            }
        }
    }

    public function toCurrentSlug(): string
    {
        return $this->currentSlug->toValue();
    }

    /**
     * @return string[]
     */
    public function toOldSlugs(): array
    {
        return array_map(
            static fn (Slug $slug): string => $slug->toValue(),
            $this->oldSlugs,
        );
    }

    /**
     * A new history with `$newSlug` as the current slug and the current
     * one appended to the recorded history.
     */
    public function reassigned(Slug $newSlug): self
    {
        return new self(
            currentSlug: $newSlug,
            oldSlugs: array_merge($this->oldSlugs, [$this->currentSlug]),
        );
    }
}

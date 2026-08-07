<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\Entities;

use App\Features\Shared\Domain\BaseEntity;
use InvalidArgumentException;

/**
 * Read-model of a published + featured project for the landing page
 * (content-management.md:116-120, contract `FeaturedProjectData`):
 * the locale-resolved title/brief description, the slug, the preview
 * image path and the tech tags.
 */
final class FeaturedProject extends BaseEntity
{
    /**
     * @param  FeaturedProjectTechTag[]  $techTags
     */
    public function __construct(
        private readonly int $id,
        private readonly string $title,
        private readonly string $slug,
        private readonly string $briefDescription,
        private readonly ?string $previewImage,
        private readonly array $techTags,
    ) {
        if ($title === '' || $slug === '') {
            throw new InvalidArgumentException('Featured project title and slug must not be empty.');
        }
    }

    public function toId(): int
    {
        return $this->id;
    }

    public function toTitle(): string
    {
        return $this->title;
    }

    public function toSlug(): string
    {
        return $this->slug;
    }

    public function toBriefDescription(): string
    {
        return $this->briefDescription;
    }

    public function toPreviewImage(): ?string
    {
        return $this->previewImage;
    }

    /**
     * @return FeaturedProjectTechTag[]
     */
    public function toTechTags(): array
    {
        return $this->techTags;
    }
}

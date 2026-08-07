<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\Entities;

use App\Features\Shared\Domain\BaseEntity;
use App\Features\Shared\Domain\ValueObjects\StoredPicture;
use InvalidArgumentException;

/**
 * Read-model of a published + featured project for the landing page
 * (content-management.md:116-120, contract `FeaturedProjectData`): the
 * locale-resolved title/brief description, the slug (kebab-case), the
 * preview image as a {@see StoredPicture} and the tech tags.
 */
final class FeaturedProject extends BaseEntity
{
    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * @param  FeaturedProjectTechTag[]  $techTags
     */
    public function __construct(
        private readonly int $id,
        private readonly string $title,
        private readonly string $slug,
        private readonly string $briefDescription,
        private readonly ?StoredPicture $previewImage,
        private readonly array $techTags,
    ) {
        if ($title === '') {
            throw new InvalidArgumentException('Featured project title must not be empty.');
        }

        $this->assertValidSlug($slug);
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

    public function toPreviewImage(): ?StoredPicture
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

    private function assertValidSlug(string $slug): void
    {
        if (preg_match(self::SLUG_PATTERN, $slug) !== 1) {
            throw new InvalidArgumentException(
                "Featured project slug must be kebab-case (lowercase letters, digits, dashes), got \"{$slug}\".",
            );
        }
    }
}

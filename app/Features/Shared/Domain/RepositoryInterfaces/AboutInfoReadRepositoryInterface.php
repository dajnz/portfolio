<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\RepositoryInterfaces;

/**
 * Read-only access to the single-row `about_info` aggregate for
 * cross-feature consumers (the site name used by the public SEO props).
 */
interface AboutInfoReadRepositoryInterface
{
    /**
     * The site name for the given locale, falling back to `en` (and to
     * an empty string when no about row exists).
     */
    public function findSiteName(string $locale): string;

    /**
     * The site title (the owner's position/role) for the given locale,
     * falling back to `en` (and to an empty string when no about row
     * exists).
     */
    public function findSiteTitle(string $locale): string;

    /**
     * The stored RustFS path of the admin's profile photo (the
     * BlogPosting `publisher.logo`), or `null` when no photo is set.
     */
    public function findSitePhotoPath(): ?string;
}

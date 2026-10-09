<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\RepositoryInterfaces;

/**
 * Read-only access to the single-row `about_info` aggregate for
 * cross-feature consumers: the site name used by the public SEO props
 * (seo.md:43). The write side and the full read side belong to the
 * Portfolio feature.
 */
interface AboutInfoReadRepositoryInterface
{
    /**
     * The site name for the given locale, falling back to `en` (and to
     * an empty string when no about row exists).
     */
    public function findSiteName(string $locale): string;

    /**
     * The stored RustFS path of the admin's profile photo (the
     * BlogPosting `publisher.logo`, seo.md:141), or `null` when no
     * photo is set.
     */
    public function findSitePhotoPath(): ?string;
}

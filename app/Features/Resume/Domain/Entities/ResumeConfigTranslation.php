<?php

declare(strict_types=1);

namespace App\Features\Resume\Domain\Entities;

use InvalidArgumentException;

/**
 * A single localized resume-config translation (`resume_config_translations`,
 * PK `(resume_config_id, locale)` — database.md:117-126): the custom
 * "About Me" text for the auto-generated resume PDF. A null `aboutText`
 * means the PDF falls back to `about_info_translations.short_pitch`
 * (content-management.md:382). Immutable; carries no identity of its own.
 */
final readonly class ResumeConfigTranslation
{
    public function __construct(
        private string $locale,
        private ?string $aboutText,
    ) {
        if ($locale === '') {
            throw new InvalidArgumentException('Resume config translation locale must not be empty.');
        }
    }

    public function toLocale(): string
    {
        return $this->locale;
    }

    public function toAboutText(): ?string
    {
        return $this->aboutText;
    }
}

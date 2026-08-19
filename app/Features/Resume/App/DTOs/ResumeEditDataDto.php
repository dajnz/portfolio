<?php

declare(strict_types=1);

namespace App\Features\Resume\App\DTOs;

/**
 * The resume configuration as consumed by the admin edit form
 * (resume.yaml#ResumeEditPageProps): the generation mode, the layout
 * JSON, the manual file path and the per-locale about texts (resolved
 * against the active locales — an empty string when no custom text is
 * stored). When the (seeder-guaranteed) single row is absent the use
 * case returns the documented defaults (content-management.md:337-350) —
 * the page is the creation surface.
 */
final readonly class ResumeEditDataDto
{
    /**
     * @param  array<string, mixed>  $layout
     * @param  array<string, string>  $aboutTexts  Locale => about text.
     */
    public function __construct(
        public string $mode,
        public array $layout,
        public ?string $customResumeFile,
        public array $aboutTexts,
    ) {
    }
}

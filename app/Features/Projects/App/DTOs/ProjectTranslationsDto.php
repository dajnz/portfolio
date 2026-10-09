<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

/**
 * The set of per-locale translations of a project — the LocaleTabs
 * maps (content-management.md:420). `title` and `brief_description`
 * are required per ACTIVE locale (validated by the request); the
 * Markdown content fields are optional. Immutable.
 */
final readonly class ProjectTranslationsDto
{
    /**
     * @param  array<string, string|null>  $titles  Locale => title.
     * @param  array<string, string|null>  $briefDescriptions  Locale => brief description.
     * @param  array<string, string|null>  $fullDescriptions  Locale => Markdown text.
     * @param  array<string, string|null>  $problems  Locale => Markdown text.
     * @param  array<string, string|null>  $solutions  Locale => Markdown text.
     */
    public function __construct(
        public array $titles = [],
        public array $briefDescriptions = [],
        public array $fullDescriptions = [],
        public array $problems = [],
        public array $solutions = [],
    ) {
    }

    /**
     * The EN title — the slug regeneration source (ADR-0006, content-
     * management.md:430 — the slug is generated from the default-locale
     * title only).
     */
    public function enTitle(): string
    {
        $title = $this->titles['en'] ?? '';

        return is_string($title) ? $title : '';
    }
}

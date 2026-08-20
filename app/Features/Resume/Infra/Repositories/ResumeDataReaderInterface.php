<?php

declare(strict_types=1);

namespace App\Features\Resume\Infra\Repositories;

/**
 * Query-builder reads of the resume PDF's source data
 * (content-management.md:377-385) inside the Resume Infra layer
 * (B11 — no cross-feature class imports). The `skills` source comes from
 * the Shared `TechSkillReadRepositoryInterface` (architecture.md:224);
 * about info, contacts and experience items are read here, locale-resolved
 * with the `en` fallback (content-management.md:419).
 */
interface ResumeDataReaderInterface
{
    /**
     * The `updated_at` stamps of every resume source row (about info,
     * skill groups, skills, contacts, experience items) — the content
     * hash input (content-management.md:393). Translation rows carry no
     * timestamps (database.md:348-361). Keyed deterministically (by id).
     *
     * @return array<string, mixed>
     */
    public function sourceStamps(): array;

    /**
     * The resume header data: about name + title for the locale (with
     * the `en` fallback). Empty strings when no about row exists.
     *
     * @return array{name: string, title: string}
     */
    public function headerData(string $locale): array;

    /**
     * The about short pitch for the locale (the About Me fallback when
     * no custom `about_text` is stored — content-management.md:382).
     */
    public function shortPitch(string $locale): string;

    /**
     * The experience items for the resume: `include_in_resume = true AND
     * is_published = true`, ordered `started_at DESC, id DESC`
     * (content-management.md:384). Tech tags are the linked skill names.
     *
     * @return array<int, array{
     *     position: string,
     *     company_name: string|null,
     *     company_country: string|null,
     *     started_at: string,
     *     ended_at: string|null,
     *     achievements: array<int, array{description: string, project_link: string|null}>,
     *     tech_tags: string[],
     * }>
     */
    public function experienceData(string $locale): array;

    /**
     * The contacts for the resume: `include_in_resume = true`, ordered by
     * `sort_order` (content-management.md:385).
     *
     * @return array<int, array{name: string, value: string, icon: string|null, url: string|null}>
     */
    public function contactsData(string $locale): array;

    /**
     * The inclusion counts for the admin summary line
     * (admin-section-ui.md:290): skills / experience items / contacts
     * with `include_in_resume = true` (soft-deleted experience items
     * excluded). The counts are the admin-page checkboxes' source —
     * no `is_published` filter (the PDF additionally requires it,
     * content-management.md:384).
     *
     * @return array{skills: int, experience: int, contacts: int}
     */
    public function inclusionCounts(): array;
}

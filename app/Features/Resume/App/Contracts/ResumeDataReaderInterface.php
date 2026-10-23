<?php

declare(strict_types=1);

namespace App\Features\Resume\App\Contracts;

/**
 * App-layer read contract for the resume PDF's source data. The `skills`
 * source comes from the Shared `TechSkillReadRepositoryInterface`; about
 * info, contacts and experience items come from this contract,
 * locale-resolved with the `en` fallback. Implemented by the Eloquent
 * data reader on the Infra layer.
 */
interface ResumeDataReaderInterface
{
    /**
     * The `updated_at` stamps of every resume source row (about info,
     * skill groups, skills, contacts, experience items) — the content
     * hash input. Translation rows carry no timestamps. Keyed
     * deterministically (by id).
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
     * no custom `about_text` is stored).
     */
    public function shortPitch(string $locale): string;

    /**
     * The experience items for the resume: `include_in_resume = true AND
     * is_published = true`, ordered `started_at DESC, id DESC`. Tech tags
     * are the linked skill names.
     *
     * @return array<int, array{
     *     position: string,
     *     company_name: string|null,
     *     company_country: string|null,
     *     started_at: string,
     *     ended_at: string|null,
     *     achievements: array<int, array{description: string}> Plain text (Markdown formatting stripped),
     *     tech_tags: string[],
     * }>
     */
    public function experienceData(string $locale): array;

    /**
     * The contacts for the resume: `include_in_resume = true`, ordered by
     * `sort_order`.
     *
     * @return array<int, array{name: string, value: string, icon: string|null, url: string|null}>
     */
    public function contactsData(string $locale): array;

    /**
     * The inclusion counts for the admin summary line: skills / experience
     * items / contacts with `include_in_resume = true` (soft-deleted
     * experience items excluded). The counts are the admin-page checkboxes'
     * source — no `is_published` filter (the PDF additionally requires it).
     *
     * @return array{skills: int, experience: int, contacts: int}
     */
    public function inclusionCounts(): array;
}

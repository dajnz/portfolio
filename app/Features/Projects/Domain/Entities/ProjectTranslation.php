<?php

declare(strict_types=1);

namespace App\Features\Projects\Domain\Entities;

use InvalidArgumentException;

/**
 * A single localized row of `project_translations` (PK
 * `(project_id, locale)` — database.md:218-232): the identity fields
 * (mandatory title + brief description) and the Markdown content fields
 * (`full_description`, `problem`, `solution` — all nullable). Immutable;
 * carries no identity of its own.
 *
 * Invariants:
 * - `title` and `brief_description` are mandatory — the only required
 *   columns (content-management.md:626-627, both NOT NULL in the DB);
 * - `locale` must be a non-empty string (the `en|ru` policy lives in
 *   the form request, which also enforces the active-locale rules);
 * - empty-string content fields normalize to null (the DB columns are
 *   nullable and the admin form may submit blank textareas).
 */
final readonly class ProjectTranslation
{
    private string $locale;

    private string $title;

    private string $briefDescription;

    private ?string $fullDescription;

    private ?string $problem;

    private ?string $solution;

    public function __construct(
        string $locale,
        string $title,
        string $briefDescription,
        ?string $fullDescription,
        ?string $problem,
        ?string $solution,
    ) {
        if ($locale === '') {
            throw new InvalidArgumentException('Project translation locale must not be empty.');
        }

        if ($title === '') {
            throw new InvalidArgumentException('Project translation title must not be empty.');
        }

        if ($briefDescription === '') {
            throw new InvalidArgumentException('Project translation brief description must not be empty.');
        }

        $this->locale = $locale;
        $this->title = $title;
        $this->briefDescription = $briefDescription;
        $this->fullDescription = $fullDescription === '' ? null : $fullDescription;
        $this->problem = $problem === '' ? null : $problem;
        $this->solution = $solution === '' ? null : $solution;
    }

    public function toLocale(): string
    {
        return $this->locale;
    }

    public function toTitle(): string
    {
        return $this->title;
    }

    public function toBriefDescription(): string
    {
        return $this->briefDescription;
    }

    public function toFullDescription(): ?string
    {
        return $this->fullDescription;
    }

    public function toProblem(): ?string
    {
        return $this->problem;
    }

    public function toSolution(): ?string
    {
        return $this->solution;
    }
}

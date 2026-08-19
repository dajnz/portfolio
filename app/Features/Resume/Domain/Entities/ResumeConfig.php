<?php

declare(strict_types=1);

namespace App\Features\Resume\Domain\Entities;

use App\Features\Shared\Domain\BaseEntity;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The single-row resume generation configuration (`resume_config`, id = 1
 * enforced at the application level — database.md:104-116): generation
 * mode, the layout JSON (template + section visibility/ordering per
 * content-management.md:335-347) and the manual-upload path. Localized
 * content lives in {@see ResumeConfigTranslation} value objects; the
 * `updated_at` stamp feeds the PDF content hash
 * (content-management.md:393). Invariants are enforced by construction:
 * the mode is one of `auto`|`manual` (the varchar + CHECK column domain),
 * the layout shape (template set, exactly the five sections, each with a
 * boolean `visible` and an integer `order` 0–4) matches the request
 * validation table (content-management.md:360-375).
 *
 * State changes go through the mutation methods; the repository persists
 * the entity afterwards.
 */
final class ResumeConfig extends BaseEntity
{
    public const ID = 1;

    private const TEMPLATES = ['modern', 'classic', 'compact'];

    private const SECTION_KEYS = ['header', 'about_me', 'skills', 'experience', 'contacts'];

    private const MODES = ['auto', 'manual'];

    /**
     * @param  array<string, mixed>  $layout  The layout JSON (template + sections map).
     * @param  ResumeConfigTranslation[]  $translations  Keyed by locale on construction.
     */
    public function __construct(
        private readonly int $id,
        private string $mode,
        private array $layout,
        private ?string $customResumeFile,
        private array $translations,
        private readonly DateTimeImmutable $updatedAt,
    ) {
        $this->validateMode($mode);
        $this->validateLayout($layout);
        $this->validateCustomResumeFile($customResumeFile);
        $this->translations = $this->translationsByLocaleMap($translations);
    }

    public function toId(): int
    {
        return $this->id;
    }

    public function toMode(): string
    {
        return $this->mode;
    }

    /**
     * `auto` = the PDF is generated from the structured portfolio data
     * (content-management.md:330).
     */
    public function isAuto(): bool
    {
        return $this->mode === 'auto';
    }

    /**
     * `manual` = the uploaded file (`custom_resume_file`) is served
     * directly (content-management.md:330,403-405).
     */
    public function isManual(): bool
    {
        return $this->mode === 'manual';
    }

    /**
     * @return array<string, mixed>
     */
    public function toLayout(): array
    {
        return $this->layout;
    }

    public function toTemplate(): string
    {
        return (string) ($this->layout['template'] ?? '');
    }

    public function toCustomResumeFile(): ?string
    {
        return $this->customResumeFile;
    }

    public function toUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return ResumeConfigTranslation[]
     */
    public function translationsByLocale(): array
    {
        return array_values($this->translations);
    }

    /**
     * The custom "About Me" text for the locale (null = fall back to the
     * about-info `short_pitch` at assembly time — content-management.md:382).
     */
    public function aboutTextFor(string $locale): ?string
    {
        $translation = $this->translations[$locale] ?? null;

        return $translation?->toAboutText();
    }

    public function changeMode(string $mode): void
    {
        $this->validateMode($mode);
        $this->mode = $mode;
    }

    /**
     * @param  array<string, mixed>  $layout
     */
    public function updateLayout(array $layout): void
    {
        $this->validateLayout($layout);
        $this->layout = $layout;
    }

    public function changeCustomResumeFile(?string $path): void
    {
        $this->validateCustomResumeFile($path);
        $this->customResumeFile = $path;
    }

    /**
     * Replaces or appends the locale's about text (null clears it).
     */
    public function updateAboutText(string $locale, ?string $aboutText): void
    {
        $this->translations[$locale] = new ResumeConfigTranslation($locale, $aboutText);
    }

    private function validateMode(string $mode): void
    {
        if (! in_array($mode, self::MODES, true)) {
            throw new InvalidArgumentException('Resume mode must be one of: '.implode(', ', self::MODES).'.');
        }
    }

    /**
     * @param  array<string, mixed>  $layout
     */
    private function validateLayout(array $layout): void
    {
        $template = $layout['template'] ?? null;

        if (! is_string($template) || ! in_array($template, self::TEMPLATES, true)) {
            throw new InvalidArgumentException("Resume layout template must be one of: ".implode(', ', self::TEMPLATES).'.');
        }

        $sections = $layout['sections'] ?? null;

        if (! is_array($sections)) {
            throw new InvalidArgumentException('Resume layout sections must be an array.');
        }

        foreach (self::SECTION_KEYS as $key) {
            $section = $sections[$key] ?? null;

            if (! is_array($section)) {
                throw new InvalidArgumentException("Resume layout section '{$key}' is missing.");
            }

            if (! isset($section['visible']) || ! is_bool($section['visible'])) {
                throw new InvalidArgumentException("Resume layout section '{$key}' must declare a boolean visible flag.");
            }

            if (! isset($section['order']) || ! is_int($section['order']) || $section['order'] < 0 || $section['order'] > 4) {
                throw new InvalidArgumentException("Resume layout section '{$key}' order must be an integer between 0 and 4.");
            }
        }
    }

    private function validateCustomResumeFile(?string $path): void
    {
        if ($path !== null && ($path === '' || mb_strlen($path) > 255)) {
            throw new InvalidArgumentException('Resume custom file path must be a non-empty string of at most 255 characters.');
        }
    }

    /**
     * @param  ResumeConfigTranslation[]  $translations
     * @return array<string, ResumeConfigTranslation>
     */
    private function translationsByLocaleMap(array $translations): array
    {
        $map = [];

        foreach ($translations as $translation) {
            $map[$translation->toLocale()] = $translation;
        }

        return $map;
    }
}

<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

/**
 * Per-locale value projections for the Infra/controllers layers — the
 * `{locale => value}` conventions shared by the admin editing surfaces:
 * normalizing raw form input (`perLocaleInput`-style helpers in the
 * controllers) and mapping entity translations to locale-keyed value
 * maps (`perLocaleMap`/`perLocaleValues`-style helpers). Deliberately
 * separate from {@see ActiveLocaleService}, which resolves the ACTIVE
 * locales from the settings repository — these projections are plain
 * data transforms.
 */
final class PerLocaleValues
{
    /**
     * Normalizes a raw per-locale form value (`{locale: mixed}`) to
     * `{locale: string|null}` — non-string entries become null.
     *
     * @param  array<int|string, mixed>  $input
     * @return array<string, string|null>
     */
    public function inputMap(array $input): array
    {
        $values = [];

        foreach ($input as $locale => $value) {
            $values[(string) $locale] = is_string($value) ? $value : null;
        }

        return $values;
    }

    /**
     * Maps a locale-keyed set of translations (e.g. an entity's
     * `translationsByLocale()`) to `{locale => extracted value}` via the
     * given extractor.
     *
     * @template T
     * @param  array<string, mixed>  $translations  Keyed by locale.
     * @param  callable(mixed): T  $extractor
     * @return array<string, T>
     */
    public function mapPerLocale(array $translations, callable $extractor): array
    {
        $map = [];

        foreach ($translations as $locale => $translation) {
            $map[(string) $locale] = $extractor($translation);
        }

        return $map;
    }
}

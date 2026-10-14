<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

/**
 * Resolves a translation key against the current locale, interpolating the
 * given replacements — the labels the app layer composes into its DTOs (SEO
 * titles, breadcrumb names, validation-flavoured messages). An abstraction
 * over the framework's translator so the app layer never calls the translation
 * helper; the framework-backed implementation lives on the infra layer.
 */
interface TranslatorInterface
{
    /**
     * @param  array<string, string|int|float>  $replace
     */
    public function translate(string $key, array $replace = []): string;
}

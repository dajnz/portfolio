<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

/**
 * The request's locale context: the locale currently being rendered and the
 * locales enabled in the `supported_locales` setting. An abstraction over the
 * framework's active locale so the app layer never reads it from the framework
 * directly; the implementation lives on the infra layer.
 */
interface ActiveLocaleInterface
{
    /** The locale of the current request. */
    public function current(): string;

    /**
     * The enabled locales, in their configured order.
     *
     * @return string[]
     */
    public function activeLocales(): array;
}

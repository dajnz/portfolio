<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Services;

use MessageFormatter;

/**
 * ICU MessageFormat formatter (i18n-and-theming.md:31, B28).
 *
 * Wraps PHP's `\MessageFormatter::format()` (an intl dependency — hence an
 * app-layer service, not a domain class) so controllers, use cases and
 * views can resolve plural/select messages across locales (English
 * one/other, Russian one/few/many). There is intentionally no global
 * `icuf()` helper — callers resolve the translated pattern (e.g. via
 * `__()`) and pass it to {@see format()} together with the arguments.
 *
 * `intl` is a hard PHP dependency of the project.
 */
final class IcuMessageFormatter
{
    /**
     * Formats an ICU MessageFormat pattern.
     *
     * @param  array<string, int|float|string|null>  $arguments
     *
     * @throws \IntlException when the pattern is not valid ICU syntax
     * @throws \InvalidArgumentException when formatting fails (arguments
     *                                   incompatible with the pattern)
     */
    public function format(string $pattern, array $arguments = [], string $locale = 'en'): string
    {
        $formatter = new MessageFormatter($locale, $pattern);

        $result = $formatter->format($arguments);

        if ($result === false) {
            throw new \InvalidArgumentException(
                sprintf('Failed to format ICU message "%s" for locale "%s": %s', $pattern, $locale, intl_get_error_message()),
            );
        }

        return $result;
    }
}

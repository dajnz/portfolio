<?php

declare(strict_types=1);

namespace App\Features\Projects\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Value object: the project's optional URL pair — the source-code
 * repository (not necessarily GitHub, hence the generic name) and the
 * live demo. Both are optional; every present URL must pass
 * `FILTER_VALIDATE_URL`. Immutable.
 */
final readonly class ProjectLinks
{
    public function __construct(
        private ?string $sourceCodeUrl = null,
        private ?string $liveDemoUrl = null,
    ) {
        if ($sourceCodeUrl !== null && filter_var($sourceCodeUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException(
                "Source-code URL must be a valid URL, got \"{$sourceCodeUrl}\"."
            );
        }

        if ($liveDemoUrl !== null && filter_var($liveDemoUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException(
                "Live demo URL must be a valid URL, got \"{$liveDemoUrl}\"."
            );
        }
    }

    public function toSourceCodeUrl(): ?string
    {
        return $this->sourceCodeUrl;
    }

    public function toLiveDemoUrl(): ?string
    {
        return $this->liveDemoUrl;
    }
}

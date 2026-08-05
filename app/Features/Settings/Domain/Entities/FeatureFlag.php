<?php

declare(strict_types=1);

namespace App\Features\Settings\Domain\Entities;

use App\Features\Settings\Domain\Enums\FeatureFlagName;
use InvalidArgumentException;

final readonly class FeatureFlag
{
    public const MAX_DESCRIPTION_LENGTH = 500;

    public function __construct(
        private FeatureFlagName $name,
        private bool $enabled,
        private ?string $description,
    ) {
        if ($description !== null && mb_strlen($description) > self::MAX_DESCRIPTION_LENGTH) {
            throw new InvalidArgumentException(
                sprintf(
                    "Feature flag description must not exceed %d characters.",
                    self::MAX_DESCRIPTION_LENGTH,
                ),
            );
        }
    }

    public static function isValidName(string $name): bool
    {
        return FeatureFlagName::tryFrom($name) !== null;
    }

    public function toName(): string
    {
        return $this->name->value;
    }

    public function toEnabled(): bool
    {
        return $this->enabled;
    }

    public function toDescription(): ?string
    {
        return $this->description;
    }
}

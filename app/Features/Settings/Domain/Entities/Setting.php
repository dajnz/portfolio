<?php

declare(strict_types=1);

namespace App\Features\Settings\Domain\Entities;

use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\Enums\SettingType;
use App\Features\Settings\Domain\ValueObjects\SettingValue;
use InvalidArgumentException;

final readonly class Setting
{
    public function __construct(
        private SettingName $name,
        private SettingValue $value,
    ) {
        if ($this->value->type !== $this->name->type()) {
            throw new InvalidArgumentException(
                sprintf(
                    "Setting '%s' must hold a %s value, got %s.",
                    $this->name->value,
                    $this->name->type()->value,
                    $this->value->type->value,
                ),
            );
        }

        $this->assertValueInDomain();
    }

    public static function isValidName(string $name): bool
    {
        return SettingName::tryFrom($name) !== null;
    }

    public function toName(): string
    {
        return $this->name->value;
    }

    public function toValue(): SettingValue
    {
        return $this->value;
    }

    public function updateValue(SettingValue $value): self
    {
        return new self($this->name, $value);
    }

    public function isBoolean(): bool
    {
        return $this->name->type() === SettingType::Boolean;
    }

    public function isString(): bool
    {
        return $this->name->type() === SettingType::String;
    }

    public function isInteger(): bool
    {
        return $this->name->type() === SettingType::Integer;
    }

    public function isJson(): bool
    {
        return $this->name->type() === SettingType::Json;
    }

    public function value(): mixed
    {
        return $this->value->value();
    }

    public function rawValue(): ?string
    {
        return $this->value->toRaw();
    }

    private function assertValueInDomain(): void
    {
        $minimum = match ($this->name) {
            SettingName::ItemsPerPage,
            SettingName::ProjectsPerPage,
            SettingName::ArticlesPerPage,
            SettingName::ExperienceItemsPerPage,
            SettingName::BackupMaxCount,
            SettingName::BackupUploadMaxSize => 1,
            SettingName::TrashRetentionDays => 0,
            default => null,
        };

        if ($minimum !== null && $this->value->asInt() < $minimum) {
            throw new InvalidArgumentException(
                sprintf("Setting '%s' must be at least %d.", $this->name->value, $minimum),
            );
        }

        // The public site must stay reachable in at least one locale —
        // disabling every locale would leave the portfolio without a
        // rendered language (i18n-and-theming.md; user request 2026-08-07).
        if ($this->name === SettingName::SupportedLocales && $this->value->asArray() === []) {
            throw new InvalidArgumentException(
                "Setting 'supported_locales' must contain at least one locale.",
            );
        }
    }
}

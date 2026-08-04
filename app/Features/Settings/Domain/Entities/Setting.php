<?php

declare(strict_types=1);

namespace App\Features\Settings\Domain\Entities;

use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\Enums\SettingType;
use App\Features\Settings\Domain\ValueObjects\SettingValue;
use InvalidArgumentException;

/**
 * One `settings` row: a strictly defined name plus a type-conformant
 * value. The value type is fixed by the name, and each name has its own
 * value domain (numeric bounds per A8, the report-frequency enum) — both
 * are enforced in the constructor, so an invalid `Setting` can never be
 * constructed.
 */
final readonly class Setting
{
    public function __construct(
        public SettingName $name,
        public SettingValue $value,
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

    /**
     * Returns a new setting with a different value; the value type and
     * domain are restricted to the name's (enforced by the constructor).
     */
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

    /**
     * Per-name value domains (A8): per-page and backup counts must be
     * positive, `trash_retention_days` may be 0 (pruning disabled), and
     * the report frequency is restricted to the documented cadences.
     */
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

        if ($this->name === SettingName::AnalyticsReportFrequency
            && ! in_array($this->value->asString(), ['daily', 'weekly', 'monthly'], true)) {
            throw new InvalidArgumentException(
                sprintf("Setting '%s' must be one of: daily, weekly, monthly.", $this->name->value),
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Features\Settings\Domain\ValueObjects;

use App\Features\Settings\Domain\Enums\SettingType;
use InvalidArgumentException;

/**
 * Immutable setting value: a `SettingType` plus a value that conforms to
 * it. Type conformance is enforced in the constructor, so an invalid
 * value can never be constructed (this is what the old validation rule
 * delegated to).
 *
 * A `null` input for a string value normalizes to `''` (an empty form
 * field); all other values must pass `SettingType::isValidValue()`.
 */
final readonly class SettingValue
{
    public SettingType $type;

    public mixed $value;

    public function __construct(SettingType $type, mixed $value)
    {
        $this->type = $type;
        $this->value = $type === SettingType::String && $value === null ? '' : $value;

        if (! $type->isValidValue($this->value)) {
            throw new InvalidArgumentException(
                sprintf("Invalid value for setting type '%s': %s.", $type->value, var_export($this->value, true)),
            );
        }
    }

    /**
     * Creates an instance from the raw DB text. Unparseable raw values
     * (corrupt rows) throw instead of silently coercing.
     */
    public static function fromRaw(SettingType $type, ?string $raw): self
    {
        if ($type === SettingType::String) {
            return new self($type, $raw);
        }

        if ($raw === null) {
            throw new InvalidArgumentException("Cannot create a {$type->value} value from null.");
        }

        return new self($type, $type->parseRaw($raw));
    }

    /**
     * Converts the internal value back to the raw DB text.
     */
    public function toRaw(): ?string
    {
        return $this->type->toRaw($this->value);
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->value === $other->value;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function asBool(): bool
    {
        $this->assertType(SettingType::Boolean);

        return filter_var($this->value, FILTER_VALIDATE_BOOLEAN);
    }

    public function asInt(): int
    {
        $this->assertType(SettingType::Integer);

        return (int) $this->value;
    }

    public function asString(): string
    {
        $this->assertType(SettingType::String);

        return $this->value;
    }

    public function asArray(): array
    {
        $this->assertType(SettingType::Json);

        return is_array($this->value) ? $this->value : (array) json_decode((string) $this->value, true);
    }

    private function assertType(SettingType $type): void
    {
        if ($this->type !== $type) {
            throw new InvalidArgumentException(
                sprintf("Setting value is of type '%s', not '%s'.", $this->type->value, $type->value),
            );
        }
    }
}

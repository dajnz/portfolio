<?php

declare(strict_types=1);

namespace App\Features\Settings\Domain\ValueObjects;

use App\Features\Settings\Domain\Enums\SettingType;
use InvalidArgumentException;

/**
 * Immutable setting value: a `SettingType` plus a value that conforms to
 * it. Type conformance is enforced in the constructor, so an invalid
 * value can never be constructed.
 *
 * A `null` input for a string value normalizes to `''` (an empty form
 * field); a numeric string for an integer value normalizes to the int
 * (Laravel's Redis cache store round-trips numeric payloads as strings —
 * `is_numeric` passthrough — so cached integer settings arrive here as
 * strings; the canonical typed value is the VO's contract).
 */
final readonly class SettingValue
{
    public SettingType $type;

    public mixed $value;

    public function __construct(SettingType $type, mixed $value)
    {
        $this->type = $type;
        $this->value = $this->normalize($type, $value);

        if (! $type->isValidValue($this->value)) {
            throw new InvalidArgumentException(
                sprintf("Invalid value for setting type '%s': %s.", $type->value, var_export($this->value, true)),
            );
        }
    }

    private function normalize(SettingType $type, mixed $value): mixed
    {
        if ($type === SettingType::String && $value === null) {
            return '';
        }

        if (
            $type === SettingType::Integer
            && is_string($value)
            && preg_match('/^-?\d+$/', $value) === 1
        ) {
            return (int) $value;
        }

        return $value;
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

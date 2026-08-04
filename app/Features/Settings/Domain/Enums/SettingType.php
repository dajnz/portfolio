<?php

declare(strict_types=1);

namespace App\Features\Settings\Domain\Enums;

/**
 * The strictly defined settings value types (database.md — the
 * `settings.type` column: string|boolean|integer|json).
 *
 * Owns the value semantics: type conformance checks and the raw (DB text)
 * parse/serialize conversions used by `SettingValue`.
 */
enum SettingType: string
{
    case String = 'string';
    case Boolean = 'boolean';
    case Integer = 'integer';
    case Json = 'json';

    /**
     * Whether the value conforms to this type. Booleans must be
     * `true`/`false`/`1`/`0`, integers must be integer values, JSON must
     * be valid JSON, strings must be strings.
     */
    public function isValidValue(mixed $value): bool
    {
        return match ($this) {
            self::Boolean => in_array($value, [true, false, 1, 0, '1', '0', 'true', 'false'], true),
            self::Integer => is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1),
            self::Json => is_string($value) ? $this->isValidJson($value) : true,
            self::String => is_string($value),
        };
    }

    /**
     * Parses raw DB text into the internal PHP value. Throws on
     * unparseable raw values (corrupt rows) instead of silently coercing.
     */
    public function parseRaw(string $raw): mixed
    {
        return match ($this) {
            self::Boolean => in_array($raw, ['1', '0', 'true', 'false'], true)
                ? in_array($raw, ['1', 'true'], true)
                : throw new \InvalidArgumentException("Invalid {$this->value} raw value: '{$raw}'."),
            self::Integer => preg_match('/^-?\d+$/', $raw) === 1
                ? (int) $raw
                : throw new \InvalidArgumentException("Invalid {$this->value} raw value: '{$raw}'."),
            self::Json => $this->decodeJson($raw),
            self::String => $raw,
        };
    }

    /**
     * Serializes the internal PHP value to DB text.
     */
    public function toRaw(mixed $value): ?string
    {
        return match ($this) {
            self::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            self::Integer => (string) $value,
            self::Json => is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE),
            self::String => (string) $value,
        };
    }

    private function isValidJson(string $value): bool
    {
        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }

    private function decodeJson(string $raw): mixed
    {
        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException("Invalid json raw value: '{$raw}'.");
        }

        return $decoded;
    }
}

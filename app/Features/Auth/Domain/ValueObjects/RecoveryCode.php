<?php

declare(strict_types=1);

namespace App\Features\Auth\Domain\ValueObjects;

use InvalidArgumentException;
use LogicException;

/**
 * Single TOTP recovery code (A5): `XXXX-XXXX-XXXX` (12 chars) drawn from
 * an unambiguous alphabet (no 0/O/1/I/L), stored bcrypt-hashed as
 * `{ "hash": string, "used": bool }`.
 *
 * Freshly generated codes keep their plaintext for one-time display;
 * codes loaded from storage only carry the hash. Hashing uses the native
 * `password_hash`/`password_verify` (bcrypt — byte-compatible with
 * Laravel's `Hash` facade) so the domain stays framework-free.
 */
final class RecoveryCode
{
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    private const LENGTH = 12;

    private function __construct(
        private readonly string $hash,
        private readonly bool $used,
        private readonly ?string $plaintext = null,
    ) {
    }

    /** Generates a new random unused code (display form `XXXX-XXXX-XXXX`). */
    public static function generate(): self
    {
        $raw = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $raw .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        $code = implode('-', str_split($raw, 4));

        return new self(hash: password_hash(self::normalize($code), PASSWORD_BCRYPT), used: false, plaintext: $code);
    }

    /**
     * @param  array{hash: string, used: bool}  $entry  stored shape
     */
    public static function fromStored(array $entry): self
    {
        if (! isset($entry['hash'], $entry['used']) || ! is_string($entry['hash']) || ! is_bool($entry['used'])) {
            throw new InvalidArgumentException('Invalid stored recovery code entry: expected {hash: string, used: bool}.');
        }

        return new self(hash: $entry['hash'], used: $entry['used']);
    }

    /**
     * Canonical form for hashing and comparison: uppercase, alphanumeric
     * only (dashes/spaces stripped, so `abcd-efgh-ijkl` and `ABCDEFGHIJKL`
     * match the same hash).
     */
    public static function normalize(string $code): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    /** Whether the normalized input matches this code's hash. */
    public function matches(string $normalizedCode): bool
    {
        return password_verify($normalizedCode, $this->hash);
    }

    public function isUsed(): bool
    {
        return $this->used;
    }

    public function markUsed(): self
    {
        return new self(hash: $this->hash, used: true, plaintext: $this->plaintext);
    }

    public function toHash(): string
    {
        return $this->hash;
    }

    /** @return array{hash: string, used: bool} */
    public function toStored(): array
    {
        return [
            'hash' => $this->hash,
            'used' => $this->used,
        ];
    }

    /**
     * The human-readable code. Only freshly generated codes carry the
     * plaintext — stored codes can never be reconstructed from the hash.
     */
    public function toDisplayCode(): string
    {
        if ($this->plaintext === null) {
            throw new LogicException('Recovery codes loaded from storage do not carry the plaintext.');
        }

        return $this->plaintext;
    }
}

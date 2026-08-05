<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain;

/**
 * Base class for domain entities.
 *
 * Every entity has a numeric identity and support identity-based equality
 * comparison. Entities reused by 2+ features live under
 * `Shared/Domain/Entities/`.
 */
abstract class BaseEntity
{
    abstract public function toId(): int;

    public function equals(self $other): bool
    {
        return $other::class === static::class && $other->toId() === $this->toId();
    }
}

<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain;

/**
 * Entities compare equal when they share the same concrete class and
 * identity (`toId()`).
 */
abstract class BaseEntity
{
    abstract public function toId(): int;

    public function equals(self $other): bool
    {
        return $other::class === static::class && $other->toId() === $this->toId();
    }
}

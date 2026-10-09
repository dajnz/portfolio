<?php

declare(strict_types=1);

namespace App\Features\Backup\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The timestamps of a `backups` row: the creation moment (always set),
 * the completion/failure stamp (set when the record completes **or**
 * fails) and the soft-delete moment. Ordering invariants: a completion
 * or deletion can only happen after the record was created.
 *
 * Immutable — the entity's state transitions replace the instance:
 * `withCompletedAt()` stamps the completion/failure moment,
 * `withoutCompletedAt()` clears it when a completed backup starts
 * re-processing.
 */
final class BackupTimings
{
    public function __construct(
        private readonly DateTimeImmutable $createdAt,
        private readonly ?DateTimeImmutable $completedAt,
        private readonly ?DateTimeImmutable $deletedAt,
    ) {
        if ($completedAt !== null && $completedAt < $createdAt) {
            throw new InvalidArgumentException('Backup completion time must not precede its creation time.');
        }

        if ($deletedAt !== null && $deletedAt < $createdAt) {
            throw new InvalidArgumentException('Backup deletion time must not precede its creation time.');
        }
    }

    public function toCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function toCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function toDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    /**
     * Stamps the completion or failure moment (the same timestamp
     * semantics as the DB column).
     */
    public function withCompletedAt(DateTimeImmutable $completedAt): self
    {
        return new self($this->createdAt, $completedAt, $this->deletedAt);
    }

    /**
     * Clears the completion stamp — the re-processing start of a
     * completed backup.
     */
    public function withoutCompletedAt(): self
    {
        return new self($this->createdAt, null, $this->deletedAt);
    }
}

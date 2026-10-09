<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Domain\Entities;

use App\Features\Shared\Domain\BaseEntity;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A feedback submission: the sender identity, the message, and the
 * server-computed anonymized identifiers. The row is immutable apart from
 * the read/forwarded flags — `created_at` only, no `updated_at`. The
 * `ipAddress`/`fingerprint` values are the keyed HMAC-SHA256 digests, never
 * the raw IP/User-Agent. Invariants are enforced in the constructor via the
 * dedicated validation methods.
 */
final class Feedback extends BaseEntity
{
    public const MIN_NAME_LENGTH = 2;

    public const MIN_MESSAGE_LENGTH = 10;

    public function __construct(
        private readonly int $id,
        private readonly string $senderName,
        private readonly string $senderEmail,
        private readonly string $message,
        private readonly string $ipAddress,
        private readonly string $fingerprint,
        private readonly bool $isRead,
        private readonly bool $isForwarded,
        private readonly DateTimeImmutable $createdAt,
    ) {
        $this->assertValidName($senderName);
        $this->assertValidEmail($senderEmail);
        $this->assertValidMessage($message);
    }

    public function toId(): int
    {
        return $this->id;
    }

    public function toSenderName(): string
    {
        return $this->senderName;
    }

    public function toSenderEmail(): string
    {
        return $this->senderEmail;
    }

    public function toMessage(): string
    {
        return $this->message;
    }

    public function toIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function toFingerprint(): string
    {
        return $this->fingerprint;
    }

    public function toIsRead(): bool
    {
        return $this->isRead;
    }

    public function toIsForwarded(): bool
    {
        return $this->isForwarded;
    }

    public function toCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function withRead(bool $isRead = true): self
    {
        return new self(
            id: $this->id,
            senderName: $this->senderName,
            senderEmail: $this->senderEmail,
            message: $this->message,
            ipAddress: $this->ipAddress,
            fingerprint: $this->fingerprint,
            isRead: $isRead,
            isForwarded: $this->isForwarded,
            createdAt: $this->createdAt,
        );
    }

    public function withForwarded(bool $isForwarded = true): self
    {
        return new self(
            id: $this->id,
            senderName: $this->senderName,
            senderEmail: $this->senderEmail,
            message: $this->message,
            ipAddress: $this->ipAddress,
            fingerprint: $this->fingerprint,
            isRead: $this->isRead,
            isForwarded: $isForwarded,
            createdAt: $this->createdAt,
        );
    }

    private function assertValidName(string $name): void
    {
        if (mb_strlen(trim($name)) < self::MIN_NAME_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('Feedback sender name must be at least %d characters.', self::MIN_NAME_LENGTH),
            );
        }
    }

    private function assertValidEmail(string $email): void
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException("Feedback sender email must be a valid email address, got \"{$email}\".");
        }
    }

    private function assertValidMessage(string $message): void
    {
        if (mb_strlen(trim($message)) < self::MIN_MESSAGE_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('Feedback message must be at least %d characters.', self::MIN_MESSAGE_LENGTH),
            );
        }
    }
}

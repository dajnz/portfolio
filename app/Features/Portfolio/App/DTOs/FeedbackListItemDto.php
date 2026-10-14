<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\DTOs;

use DateTimeImmutable;

/**
 * A feedback entry as an admin list row. Immutable.
 */
final readonly class FeedbackListItemDto
{
    public function __construct(
        public int $id,
        public string $senderName,
        public string $senderEmail,
        public string $message,
        public bool $isRead,
        public bool $isForwarded,
        public DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param  object  $row  The feedback row.
     */
    public static function fromRow(object $row): self
    {
        return new self(
            id: (int) $row->id,
            senderName: (string) $row->sender_name,
            senderEmail: (string) $row->sender_email,
            message: (string) $row->message,
            isRead: (bool) $row->is_read,
            isForwarded: (bool) $row->is_forwarded,
            createdAt: DateTimeImmutable::createFromInterface($row->created_at),
        );
    }
}

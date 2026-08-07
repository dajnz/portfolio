<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Mappers;

use App\Features\Portfolio\Domain\Entities\Feedback;
use App\Features\Portfolio\Infra\Models\FeedbackModel;

/**
 * Converts between `feedback` rows and {@see Feedback} entities. The
 * model's Carbon timestamps are converted to `DateTimeImmutable` for
 * the domain (no Carbon in the domain layer).
 */
final class FeedbackMapper
{
    public function toEntity(FeedbackModel $model): Feedback
    {
        return new Feedback(
            id: $model->id,
            senderName: $model->sender_name,
            senderEmail: $model->sender_email,
            message: $model->message,
            ipAddress: $model->ip_address,
            fingerprint: $model->fingerprint,
            isRead: $model->is_read,
            isForwarded: $model->is_forwarded,
            createdAt: $model->created_at->toDateTimeImmutable(),
        );
    }

    /**
     * @return array{
     *     sender_name: string,
     *     sender_email: string,
     *     message: string,
     *     ip_address: string,
     *     fingerprint: string,
     *     is_read: bool,
     *     is_forwarded: bool,
     * }
     */
    public function toRow(Feedback $feedback): array
    {
        return [
            'sender_name' => $feedback->toSenderName(),
            'sender_email' => $feedback->toSenderEmail(),
            'message' => $feedback->toMessage(),
            'ip_address' => $feedback->toIpAddress(),
            'fingerprint' => $feedback->toFingerprint(),
            'is_read' => $feedback->toIsRead(),
            'is_forwarded' => $feedback->toIsForwarded(),
        ];
    }
}

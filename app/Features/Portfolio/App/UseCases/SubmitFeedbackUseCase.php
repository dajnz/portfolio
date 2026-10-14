<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\UseCases;

use App\Features\Portfolio\App\Contracts\FeedbackNotificationMailerInterface;
use App\Features\Portfolio\Domain\Entities\Feedback;
use App\Features\Portfolio\Domain\RepositoryInterfaces\FeedbackRepositoryInterface;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use App\Features\Shared\App\Contracts\AnalyticsConfigInterface;
use DateTimeImmutable;

/**
 * Stores a valid feedback submission: the IP + User-Agent identifiers are
 * computed server-side as keyed HMAC-SHA256 digests (the raw values never
 * leave the request), and the admin notification mail is sent through the
 * mailer port when the `email_on_feedback` setting is on (default on).
 */
final class SubmitFeedbackUseCase
{
    public function __construct(
        private readonly FeedbackRepositoryInterface $repository,
        private readonly SettingRepositoryInterface $settings,
        private readonly AnalyticsConfigInterface $analyticsConfig,
        private readonly FeedbackNotificationMailerInterface $feedbackMailer,
    ) {
    }

    public function submit(
        string $senderName,
        string $senderEmail,
        string $message,
        string $ip,
        string $userAgent,
    ): void {
        $hashKey = $this->analyticsConfig->ipHashKey();

        $feedback = new Feedback(
            id: 0,
            senderName: $senderName,
            senderEmail: $senderEmail,
            message: $message,
            ipAddress: hash_hmac('sha256', $ip, $hashKey),
            fingerprint: hash_hmac('sha256', $ip.'|'.$userAgent, $hashKey),
            isRead: false,
            isForwarded: false,
            createdAt: new DateTimeImmutable(),
        );

        $this->repository->store($feedback);

        if ($this->settings->value(SettingName::EmailOnFeedback, true)) {
            $this->feedbackMailer->notifyAdmin($senderName, $senderEmail, $message);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\UseCases;

use App\Features\Portfolio\Domain\Entities\Feedback;
use App\Features\Portfolio\Domain\RepositoryInterfaces\FeedbackRepositoryInterface;
use App\Features\Portfolio\Infra\Mail\NotifyAdminOfFeedbackMail;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\Mail;

/**
 * Stores a valid feedback submission (content-management.md:267-273):
 * the IP + User-Agent identifiers are computed server-side as keyed
 * HMAC-SHA256 digests (B4 — the raw values never leave the request), and
 * the queued admin notification mail is dispatched when the
 * `email_on_feedback` setting is on (default on).
 */
final class SubmitFeedbackUseCase
{
    public function __construct(
        private readonly FeedbackRepositoryInterface $repository,
        private readonly SettingRepositoryInterface $settings,
    ) {
    }

    public function submit(
        string $senderName,
        string $senderEmail,
        string $message,
        string $ip,
        string $userAgent,
    ): void {
        $hashKey = config('analytics.ip_hash_key', config('app.key'));

        $feedback = new Feedback(
            id: 0,
            senderName: $senderName,
            senderEmail: $senderEmail,
            message: $message,
            ipAddress: hash_hmac('sha256', $ip, (string) $hashKey),
            fingerprint: hash_hmac('sha256', $ip.'|'.$userAgent, (string) $hashKey),
            isRead: false,
            isForwarded: false,
            createdAt: new DateTimeImmutable(),
        );

        $this->repository->store($feedback);

        if ($this->settings->value(SettingName::EmailOnFeedback, true)) {
            Mail::to((string) config('mail.from.address'))->send(
                new NotifyAdminOfFeedbackMail($senderName, $senderEmail, $message),
            );
        }
    }
}

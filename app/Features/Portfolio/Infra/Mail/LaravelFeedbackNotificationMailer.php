<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Mail;

use App\Features\Portfolio\App\Contracts\FeedbackNotificationMailerInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

/**
 * The framework mail stack behind the app-layer
 * {@see FeedbackNotificationMailerInterface} contract — resolves the admin
 * recipient from the configured `mail.from.address`.
 */
final class LaravelFeedbackNotificationMailer implements FeedbackNotificationMailerInterface
{
    public function notifyAdmin(string $senderName, string $senderEmail, string $message): void
    {
        Mail::to((string) Config::get('mail.from.address'))->send(
            new NotifyAdminOfFeedbackMail($senderName, $senderEmail, $message),
        );
    }
}

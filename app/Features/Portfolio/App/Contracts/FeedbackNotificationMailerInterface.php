<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\Contracts;

/**
 * Notifies the admin that a new feedback submission arrived. An abstraction
 * over the mail stack so the app layer never builds a mailable, resolves the
 * sender address or names the mailer; the framework-backed implementation
 * lives on the infra layer.
 */
interface FeedbackNotificationMailerInterface
{
    public function notifyAdmin(string $senderName, string $senderEmail, string $message): void;
}

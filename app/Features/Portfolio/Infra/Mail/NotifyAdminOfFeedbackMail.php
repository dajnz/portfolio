<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Queued "new feedback received" admin notification: sender identity plus
 * an excerpt — the first 120 characters of the message, the same
 * truncation as the admin feedback list. Dispatched by
 * `SubmitFeedbackUseCase` when the `email_on_feedback` setting is on; the
 * mail config is merged at boot by `SettingsMailConfigProvider`, so queue
 * workers see the DB values.
 */
final class NotifyAdminOfFeedbackMail extends Mailable implements ShouldQueue
{
    use Queueable;

    use SerializesModels;

    public const EXCERPT_LENGTH = 120;

    public function __construct(
        public readonly string $senderName,
        public readonly string $senderEmail,
        public readonly string $message,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.feedback_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.notify-admin-of-feedback',
            with: [
                'senderName' => $this->senderName,
                'senderEmail' => $this->senderEmail,
                'excerpt' => $this->excerpt(),
            ],
        );
    }

    public function excerpt(): string
    {
        $excerpt = trim($this->message);

        return mb_strlen($excerpt) > self::EXCERPT_LENGTH
            ? mb_substr($excerpt, 0, self::EXCERPT_LENGTH)
            : $excerpt;
    }
}

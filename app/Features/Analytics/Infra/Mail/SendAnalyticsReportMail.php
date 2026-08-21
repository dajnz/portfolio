<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Mail;

use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Queued analytics report mail (analytics.md:189-194, architecture.md:
 * 172): a simple HTML table with the key metrics of the four report
 * types, sent when `analytics_email_enabled` is on and a recipient is
 * configured. The mailable is queued (ShouldQueue) — dispatched via
 * `Mail::to(...)->send()`, which queues ShouldQueue mailables.
 */
final class SendAnalyticsReportMail extends Mailable implements ShouldQueue
{
    use Queueable;

    use SerializesModels;

    /**
     * @param array{
     *     overall: array<string, mixed>,
     *     blog: array<string, mixed>,
     *     projects: array<string, mixed>,
     *     feedback: array<string, mixed>,
     * } $reports
     */
    public function __construct(
        public readonly array $reports,
        public readonly DateTimeImmutable $periodStart,
        public readonly DateTimeImmutable $periodEnd,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.analytics_subject', ['period' => $this->periodLabel()]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.analytics-report',
            with: [
                'reports' => $this->reports,
                'periodLabel' => $this->periodLabel(),
            ],
        );
    }

    public function periodLabel(): string
    {
        return $this->periodStart->format('M j')
            .' – '
            .$this->periodEnd->format('M j, Y');
    }
}

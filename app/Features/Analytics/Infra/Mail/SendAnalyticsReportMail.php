<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Mail;

use App\Features\Analytics\App\DTOs\AnalyticsDigestDto;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Queued analytics digest mail (analytics.md:214-219, architecture.md:
 * 172, ADR-0017): a simple HTML table with the previous day's key metrics
 * (page views, unique visitors, feedback submissions) and the top
 * article by views, sent when `analytics_email_enabled` is on and a
 * recipient is configured. The mailable is queued (ShouldQueue) —
 * dispatched via `Mail::to(...)->send()`, which queues ShouldQueue
 * mailables. The digest is built by `GetDailyDigestUseCase` (11.3.5)
 * before the mailable is queued, so the queued payload is plain data.
 */
final class SendAnalyticsReportMail extends Mailable implements ShouldQueue
{
    use Queueable;

    use SerializesModels;

    public function __construct(
        public readonly AnalyticsDigestDto $digest,
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
                'digest' => $this->digest,
                'periodLabel' => $this->periodLabel(),
            ],
        );
    }

    public function periodLabel(): string
    {
        return $this->digest->day->format('M j, Y');
    }
}

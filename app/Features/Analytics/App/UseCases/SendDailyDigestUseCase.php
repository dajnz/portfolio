<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\UseCases;

use App\Features\Analytics\Infra\Mail\SendAnalyticsReportMail;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\Mail;

/**
 * The daily digest email (analytics.md:214-222, B294, 11.3.5): queues
 * `SendAnalyticsReportMail` for the day when the analytics email
 * setting is enabled with a recipient set — otherwise the digest is
 * skipped silently. The decision lives here (not in the aggregation
 * job) so the job stays a thin orchestration shell.
 */
final class SendDailyDigestUseCase
{
    public function __construct(
        private readonly GetDailyDigestUseCase $digest,
        private readonly SettingRepositoryInterface $settings,
    ) {
    }

    public function sendForDay(DateTimeImmutable $day): void
    {
        $enabled = (bool) $this->settings->value(SettingName::AnalyticsEmailEnabled, false);
        $recipient = (string) $this->settings->value(SettingName::AnalyticsEmailRecipient, '');

        if (!$enabled || $recipient === '') {
            return;
        }

        Mail::to($recipient)->send(new SendAnalyticsReportMail($this->digest->digest($day)));
    }
}

<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\UseCases;

use App\Features\Analytics\App\Contracts\AnalyticsDigestMailerInterface;
use App\Features\Analytics\App\Services\DailyDigestBuilder;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use DateTimeImmutable;

/**
 * The daily digest email: sends the day's digest through the mailer port when
 * the analytics email setting is enabled with a recipient set — otherwise the
 * digest is skipped silently. The decision lives here (not in the aggregation
 * job) so the job stays a thin orchestration shell.
 */
final class SendDailyDigestUseCase
{
    public function __construct(
        private readonly DailyDigestBuilder $digest,
        private readonly SettingRepositoryInterface $settings,
        private readonly AnalyticsDigestMailerInterface $mailer,
    ) {
    }

    public function sendForDay(DateTimeImmutable $day): void
    {
        $enabled = (bool) $this->settings->value(SettingName::AnalyticsEmailEnabled, false);
        $recipient = (string) $this->settings->value(SettingName::AnalyticsEmailRecipient, '');

        if (!$enabled || $recipient === '') {
            return;
        }

        $this->mailer->sendDailyDigest($recipient, $this->digest->digest($day));
    }
}

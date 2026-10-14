<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Mail;

use App\Features\Analytics\App\Contracts\AnalyticsDigestMailerInterface;
use App\Features\Analytics\App\DTOs\AnalyticsDigestDto;
use Illuminate\Support\Facades\Mail;

/**
 * The framework mail stack behind the app-layer
 * {@see AnalyticsDigestMailerInterface} contract.
 */
final class LaravelAnalyticsDigestMailer implements AnalyticsDigestMailerInterface
{
    public function sendDailyDigest(string $recipient, AnalyticsDigestDto $digest): void
    {
        Mail::to($recipient)->send(new SendAnalyticsReportMail($digest));
    }
}

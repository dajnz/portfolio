<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\Contracts;

use App\Features\Analytics\App\DTOs\AnalyticsDigestDto;

/**
 * Sends the daily analytics digest email. An abstraction over the mail stack
 * so the app layer never builds a mailable or names the mailer; the
 * framework-backed implementation lives on the infra layer.
 */
interface AnalyticsDigestMailerInterface
{
    public function sendDailyDigest(string $recipient, AnalyticsDigestDto $digest): void;
}

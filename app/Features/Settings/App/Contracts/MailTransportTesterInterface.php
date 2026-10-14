<?php

declare(strict_types=1);

namespace App\Features\Settings\App\Contracts;

use App\Features\Settings\App\Enums\MailTransportProbeStatus;

/**
 * Probes the 3rd-party mail API with a bearer token without sending mail — the
 * "Test Connection" button. An abstraction over the HTTP client so the app
 * layer never names it (nor its network exceptions); the framework-backed
 * implementation lives on the infra layer.
 */
interface MailTransportTesterInterface
{
    public function probe(string $endpoint, string $token): MailTransportProbeStatus;
}

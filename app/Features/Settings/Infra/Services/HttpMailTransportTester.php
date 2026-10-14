<?php

declare(strict_types=1);

namespace App\Features\Settings\Infra\Services;

use App\Features\Settings\App\Contracts\MailTransportTesterInterface;
use App\Features\Settings\App\Enums\MailTransportProbeStatus;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The framework HTTP client behind the app-layer
 * {@see MailTransportTesterInterface} contract: a 10 s `GET` with the bearer
 * token. A rejected token reads as `unauthorized`; a network/timeout error as
 * `unreachable`; any other response proves the endpoint is reachable.
 */
final class HttpMailTransportTester implements MailTransportTesterInterface
{
    public function probe(string $endpoint, string $token): MailTransportProbeStatus
    {
        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->acceptJson()
                ->get($endpoint);
        } catch (Throwable) {
            return MailTransportProbeStatus::Unreachable;
        }

        if ($response->unauthorized() || $response->forbidden()) {
            return MailTransportProbeStatus::Unauthorized;
        }

        return MailTransportProbeStatus::Ok;
    }
}

<?php

declare(strict_types=1);

namespace App\Features\Settings\App\Enums;

/**
 * The outcome of probing the 3rd-party mail API: reachable and authorized,
 * reachable but the token was rejected, or unreachable (network/timeout).
 */
enum MailTransportProbeStatus: string
{
    case Ok = 'ok';
    case Unauthorized = 'unauthorized';
    case Unreachable = 'unreachable';
}

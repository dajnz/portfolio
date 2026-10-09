<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * The client identifiers carried by every analytics event (ADR-0017):
 * the session id, the anonymized IP hash (keyed HMAC-SHA256, ADR-0017) and
 * the User-Agent. The validation of these properties lives here (they
 * moved out of `AnalyticsEvent`).
 */
final class ClientIds
{
    public const MAX_SESSION_ID_LENGTH = 255;

    public const MAX_IP_HASH_LENGTH = 64;

    public function __construct(
        private readonly string $sessionId,
        private readonly ?string $ipAddress,
        private readonly ?string $userAgent,
    ) {
        if ($sessionId === '') {
            throw new InvalidArgumentException('Session id must not be empty.');
        }

        if (mb_strlen($sessionId) > self::MAX_SESSION_ID_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('Session id must not exceed %d characters.', self::MAX_SESSION_ID_LENGTH),
            );
        }

        if ($ipAddress !== null && mb_strlen($ipAddress) > self::MAX_IP_HASH_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('Ip hash must not exceed %d characters.', self::MAX_IP_HASH_LENGTH),
            );
        }
    }

    public function toSessionId(): string
    {
        return $this->sessionId;
    }

    public function toIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function toUserAgent(): ?string
    {
        return $this->userAgent;
    }
}

<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\Exceptions;

use RuntimeException;

/**
 * A batch the analytics domain cannot materialize (unknown event type or
 * malformed payload) — the whole batch is rejected. The controller maps it to
 * the 422 `validation.analytics.event_invalid` response.
 */
final class InvalidAnalyticsEventException extends RuntimeException
{
}

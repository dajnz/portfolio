<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Logging;

use App\Features\Shared\Infra\Middleware\LogContextMiddleware;
use Illuminate\Log\Logger;
use Monolog\LogRecord;

/**
 * Monolog channel `tap` (config/logging.php) that redacts log-context values
 * whose key matches `LogContextMiddleware::SENSITIVE_FIELDS` — the single
 * source of truth shared with the slow-query binding filter. Applied to the
 * `stack` members (`stderr`, `daily`), so sensitive fields never reach the log
 * files or the Docker stream.
 */
final class SensitiveFieldsTap
{
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(function (LogRecord $record): LogRecord {
            $context = $record->context;

            foreach ($context as $key => $value) {
                if (is_string($key) && in_array($key, LogContextMiddleware::SENSITIVE_FIELDS, true)) {
                    $context[$key] = '[REDACTED]';
                }
            }

            return $record->with(context: $context);
        });
    }
}

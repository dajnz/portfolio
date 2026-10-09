<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Logging;

use App\Features\Shared\App\Contracts\LoggerInterface;
use Illuminate\Log\LogManager;
use Psr\Log\LoggerInterface as PsrLoggerInterface;

/**
 * The app-layer {@see LoggerInterface} over the framework logging stack.
 * Bound in `AppServiceProvider`; `channel()` returns a logger scoped to
 * the named channel (e.g. `analytics`).
 */
final class LaravelLogger implements LoggerInterface
{
    public function __construct(
        private readonly LogManager $manager,
        private readonly ?string $channel = null,
    ) {
    }

    public function info(string $message, array $context = []): void
    {
        $this->writer()->info($message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->writer()->warning($message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->writer()->error($message, $context);
    }

    public function channel(string $channel): LoggerInterface
    {
        return new self($this->manager, $channel);
    }

    private function writer(): PsrLoggerInterface
    {
        return $this->channel === null
            ? $this->manager
            : $this->manager->channel($this->channel);
    }
}

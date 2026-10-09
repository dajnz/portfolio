<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

/**
 * The app-layer logging contract (error-handling.md:226-240): app-layer
 * services log through this interface, never through a framework or
 * Infra logger. The Infra implementation
 * ({@see \App\Features\Shared\Infra\Logging\LaravelLogger}) wraps the
 * framework logging stack and is bound in `AppServiceProvider`.
 *
 * `channel()` scopes writes to a named log channel (e.g. the `analytics`
 * pipeline channel required by error-handling.md); the default writer
 * targets the configured default channel.
 */
interface LoggerInterface
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $message, array $context = []): void;

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string $message, array $context = []): void;

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $message, array $context = []): void;

    /**
     * A logger scoped to the named channel.
     */
    public function channel(string $channel): self;
}

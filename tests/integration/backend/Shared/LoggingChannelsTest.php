<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Shared;

use App\Features\Shared\Infra\Logging\SensitiveFieldsTap;
use App\Features\Shared\Infra\Middleware\LogContextMiddleware;
use Illuminate\Log\Logger as IlluminateLogger;
use Monolog\Handler\TestHandler;
use Monolog\Logger as Monolog;

describe('LoggingChannels', function (): void {
    it('defines the analytics channel per error-handling.md', function (): void {
        $channel = config('logging.channels.analytics');

        expect($channel['driver'])->toBe('daily')
            ->and($channel['level'])->toBe('info')
            ->and($channel['days'])->toBe(90)
            ->and(config('logging.channels.daily.days'))->toBe(14);
    });

    it('defaults the stack to stderr + daily', function (): void {
        expect(config('logging.channels.stack.channels'))->toBe(['stderr', 'daily']);
    });

    it('redacts sensitive context fields on the stack members via the shared constant', function (): void {
        foreach (['stderr', 'daily'] as $channel) {
            expect(config("logging.channels.{$channel}.tap"))->toContain(SensitiveFieldsTap::class);
        }
    });

    it('redacts sensitive context values when the tap is applied', function (): void {
        $handler = new TestHandler();
        $logger = new IlluminateLogger(new Monolog('test', [$handler]));

        (new SensitiveFieldsTap())($logger);

        $logger->info('safe message', [
            'password' => 'hunter2',
            'email' => 'admin@example.com',
            'request_id' => 'abc-123',
        ]);

        $record = $handler->getRecords()[0];

        expect($record['context']['password'])->toBe('[REDACTED]')
            ->and($record['context']['email'])->toBe('[REDACTED]')
            ->and($record['context']['request_id'])->toBe('abc-123');
    });

    it('shares the SENSITIVE_FIELDS constant as the single source of truth', function (): void {
        expect(LogContextMiddleware::SENSITIVE_FIELDS)->toContain('password')
            ->and(LogContextMiddleware::SENSITIVE_FIELDS)->toContain('totp_secret')
            ->and(LogContextMiddleware::SENSITIVE_FIELDS)->toContain('fingerprint');
    });
});

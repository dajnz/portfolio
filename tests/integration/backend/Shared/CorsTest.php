<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Shared;

describe('Cors', function (): void {
    it('allows CORS only for the configured application origin', function (): void {
        $allowedOrigin = (string) config('cors.allowed_origins.0');

        $allowed = $this->options('/api/analytics/track', [], [
            'Origin' => $allowedOrigin,
            'Access-Control-Request-Method' => 'POST',
        ]);
        $allowed->assertNoContent();
        expect($allowed->headers->get('Access-Control-Allow-Origin'))->toBe($allowedOrigin);

        $blocked = $this->options('/api/analytics/track', [], [
            'Origin' => 'https://evil.example',
            'Access-Control-Request-Method' => 'POST',
        ]);
        $blocked->assertNoContent();
        // A single configured origin is advertised unconditionally; the
        // browser rejects the response because it does not match the
        // requesting (evil) origin.
        expect($blocked->headers->get('Access-Control-Allow-Origin'))
            ->toBe($allowedOrigin)
            ->not->toBe('https://evil.example');
    });
});

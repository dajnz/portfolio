<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Shared;

use Illuminate\Support\Facades\Route;

describe('RateLimitWithFingerprintMiddleware', function (): void {
    beforeEach(function (): void {
        Route::post('/test/fingerprint-limit', fn () => response()->json(['ok' => true]))
            ->middleware('rate_limit.fingerprint');
    });

    it('allows 3 requests per IP+UA fingerprint and rejects the 4th with 429', function (): void {
        foreach (range(1, 3) as $attempt) {
            $this->postJson('/test/fingerprint-limit')
                ->assertOk()
                ->assertExactJson(['ok' => true]);
        }

        $this->postJson('/test/fingerprint-limit')
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    });

    it('keys the limit by IP and User-Agent separately', function (): void {
        foreach (range(1, 3) as $attempt) {
            $this->postJson('/test/fingerprint-limit')->assertOk();
        }

        // Same IP, different UA → different fingerprint → allowed.
        $this->withHeader('User-Agent', 'other-agent')
            ->postJson('/test/fingerprint-limit')
            ->assertOk();

        // Same UA, different IP → allowed.
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.10.10'])
            ->postJson('/test/fingerprint-limit')
            ->assertOk();
    });
});

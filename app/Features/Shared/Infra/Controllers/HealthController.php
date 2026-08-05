<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Health endpoints (devops.md:815-824). Both routes are registered outside
 * the `web` middleware group (no auth, no session, no locale, no CSP).
 * `/up` only confirms the process is alive; `/up/health` probes DB, Redis
 * and RustFS and caches its result for 5 seconds in the application cache.
 */
final class HealthController
{
    private const CHECKS_CACHE_KEY = 'health:checks';

    private const CHECKS_CACHE_TTL_SECONDS = 5;

    public function up(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    public function health(): JsonResponse
    {
        $checks = Cache::remember(
            self::CHECKS_CACHE_KEY,
            self::CHECKS_CACHE_TTL_SECONDS,
            fn (): array => [
                'db' => $this->checkDb(),
                'redis' => $this->checkRedis(),
                'rustfs' => $this->checkRustfs(),
            ],
        );

        $allOk = $checks['db'] === 'ok' && $checks['redis'] === 'ok' && $checks['rustfs'] === 'ok';

        return response()->json([
            'status' => $allOk ? 'ok' : 'error',
            'checks' => $checks,
        ], $allOk ? 200 : 503);
    }

    private function checkDb(): string
    {
        try {
            DB::select('SELECT 1');

            return 'ok';
        } catch (Throwable) {
            return 'error';
        }
    }

    private function checkRedis(): string
    {
        try {
            Redis::ping();

            return 'ok';
        } catch (Throwable) {
            return 'error';
        }
    }

    private function checkRustfs(): string
    {
        try {
            Storage::disk('rustfs')->files('/');

            return 'ok';
        } catch (Throwable) {
            return 'error';
        }
    }
}

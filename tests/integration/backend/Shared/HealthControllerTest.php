<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Shared;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

describe('HealthController', function (): void {
    beforeEach(function (): void {
        Storage::fake('rustfs');
    });

    it('returns {"status":"ok"} from /up with no dependency checks', function (): void {
        $this->get('/up')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    });

    it('reports all checks ok from /up/health', function (): void {
        Redis::shouldReceive('ping')->andReturn(true);

        $this->get('/up/health')
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'checks' => ['db' => 'ok', 'redis' => 'ok', 'rustfs' => 'ok'],
            ]);
    });

    it('caches the health result for 5 seconds', function (): void {
        Redis::shouldReceive('ping')->andReturn(true);

        $this->get('/up/health')->assertOk();

        expect(Cache::has('health:checks'))->toBeTrue();
    });

    it('returns 503 with the failing check when a dependency is down', function (): void {
        Redis::shouldReceive('ping')->andThrow(new \Exception('redis is down'));

        $this->get('/up/health')
            ->assertStatus(503)
            ->assertJson([
                'status' => 'error',
                'checks' => ['db' => 'ok', 'redis' => 'error', 'rustfs' => 'ok'],
            ]);
    });
});

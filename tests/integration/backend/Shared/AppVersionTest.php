<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Shared;

use App\Features\Shared\Infra\Services\AppVersion;
use App\Features\Shared\Infra\Services\GitVersionReader;
use Tests\Support\Backend\GitFixture;

describe('AppVersion', function (): void {
    it('prefers an explicitly configured version', function (): void {
        config()->set('app.version', 'v9.9.9');

        $version = new AppVersion(new GitVersionReader(), '/does-not-exist');

        expect($version->value())->toBe('v9.9.9');
    });

    it('falls back to the repository git state', function (): void {
        config()->set('app.version', '');

        $commit = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0';

        $value = GitFixture::with([
            '.git/HEAD' => "ref: refs/heads/main\n",
            '.git/refs/heads/main' => $commit . "\n",
            '.git/refs/tags/v1.4.0' => $commit . "\n",
        ], fn (string $root): string => (new AppVersion(new GitVersionReader(), $root))->value());

        expect($value)->toBe('v1.4.0');
    });

    it('falls back to `dev` when nothing can be resolved', function (): void {
        config()->set('app.version', '');

        $missing = sys_get_temp_dir() . '/no-git-' . bin2hex(random_bytes(6));

        $version = new AppVersion(new GitVersionReader(), $missing);

        expect($version->value())->toBe('dev');
    });
});

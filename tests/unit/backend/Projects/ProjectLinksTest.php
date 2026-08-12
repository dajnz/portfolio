<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Projects;

use App\Features\Projects\Domain\ValueObjects\ProjectLinks;
use InvalidArgumentException;

/**
 * The {@see ProjectLinks} VO: both links are optional, every present
 * URL must pass `FILTER_VALIDATE_URL` (content-management.md:147).
 */
describe('ProjectLinks', function (): void {
    it('accepts a valid URL pair and allows both links to be absent', function (): void {
        $links = new ProjectLinks(
            sourceCodeUrl: 'https://github.com/example/gateway',
            liveDemoUrl: 'https://demo.example.com',
        );

        expect($links->toSourceCodeUrl())->toBe('https://github.com/example/gateway')
            ->and($links->toLiveDemoUrl())->toBe('https://demo.example.com')
            ->and((new ProjectLinks())->toSourceCodeUrl())->toBeNull();
    });

    it('rejects malformed URLs', function (): void {
        expect(fn () => new ProjectLinks(sourceCodeUrl: 'not-a-url'))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn () => new ProjectLinks(liveDemoUrl: 'ftp://'))
            ->toThrow(InvalidArgumentException::class);
    });
});

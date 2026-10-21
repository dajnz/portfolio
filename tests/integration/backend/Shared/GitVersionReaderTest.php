<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Shared;

use App\Features\Shared\Infra\Services\GitVersionReader;
use Tests\Support\Backend\GitFixture;

const COMMIT_SHA = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0';
const SHORT_SHA = 'a1b2c3d';

describe('GitVersionReader', function (): void {
    it('reads the short SHA of the checked-out branch', function (): void {
        $version = GitFixture::with([
            '.git/HEAD' => "ref: refs/heads/main\n",
            '.git/refs/heads/main' => COMMIT_SHA . "\n",
        ], fn (string $root): ?string => (new GitVersionReader())->read($root));

        expect($version)->toBe(SHORT_SHA);
    });

    it('prefers the exact tag of the checked-out commit', function (): void {
        $version = GitFixture::with([
            '.git/HEAD' => "ref: refs/heads/main\n",
            '.git/refs/heads/main' => COMMIT_SHA . "\n",
            '.git/refs/tags/v1.4.0' => COMMIT_SHA . "\n",
        ], fn (string $root): ?string => (new GitVersionReader())->read($root));

        expect($version)->toBe('v1.4.0');
    });

    it('picks the highest semantic tag when several point at the commit', function (): void {
        $version = GitFixture::with([
            '.git/HEAD' => "ref: refs/heads/main\n",
            '.git/refs/heads/main' => COMMIT_SHA . "\n",
            '.git/refs/tags/v1.4.0' => COMMIT_SHA . "\n",
            '.git/refs/tags/v1.10.0' => COMMIT_SHA . "\n",
            '.git/refs/tags/v1.9.0' => COMMIT_SHA . "\n",
        ], fn (string $root): ?string => (new GitVersionReader())->read($root));

        expect($version)->toBe('v1.10.0');
    });

    it('resolves refs and tags from packed-refs', function (): void {
        $version = GitFixture::with([
            '.git/HEAD' => "ref: refs/heads/main\n",
            '.git/packed-refs' => "# pack-refs with: peeled fully-peeled sorted\n"
                . COMMIT_SHA . " refs/heads/main\n"
                . COMMIT_SHA . " refs/tags/v2.0.0\n",
        ], fn (string $root): ?string => (new GitVersionReader())->read($root));

        expect($version)->toBe('v2.0.0');
    });

    it('peels annotated tags recorded in packed-refs', function (): void {
        $tagObject = str_repeat('f', 40);

        $version = GitFixture::with([
            '.git/HEAD' => "ref: refs/heads/main\n",
            '.git/packed-refs' => COMMIT_SHA . " refs/heads/main\n"
                . $tagObject . " refs/tags/v2.1.0\n"
                . '^' . COMMIT_SHA . "\n",
        ], fn (string $root): ?string => (new GitVersionReader())->read($root));

        expect($version)->toBe('v2.1.0');
    });

    it('peels a loose annotated tag object', function (): void {
        $tagObject = str_repeat('f', 40);
        $body = "object " . COMMIT_SHA . "\ntype commit\ntag v3.0.0\n\nrelease\n";

        $version = GitFixture::with([
            '.git/HEAD' => "ref: refs/heads/main\n",
            '.git/refs/heads/main' => COMMIT_SHA . "\n",
            '.git/refs/tags/v3.0.0' => $tagObject . "\n",
            '.git/objects/ff/' . str_repeat('f', 38) => gzcompress('tag ' . strlen($body) . "\0" . $body),
        ], fn (string $root): ?string => (new GitVersionReader())->read($root));

        expect($version)->toBe('v3.0.0');
    });

    it('reads a detached HEAD commit', function (): void {
        $version = GitFixture::with([
            '.git/HEAD' => COMMIT_SHA . "\n",
            '.git/refs/tags/v3.1.0' => COMMIT_SHA . "\n",
        ], fn (string $root): ?string => (new GitVersionReader())->read($root));

        expect($version)->toBe('v3.1.0');
    });

    it('follows a worktree gitdir pointer', function (): void {
        $version = GitFixture::with([
            '.git' => "gitdir: git-store\n",
            'git-store/HEAD' => "ref: refs/heads/main\n",
            'git-store/refs/heads/main' => COMMIT_SHA . "\n",
        ], fn (string $root): ?string => (new GitVersionReader())->read($root));

        expect($version)->toBe(SHORT_SHA);
    });

    it('returns null when the directory is not a repository', function (): void {
        $version = GitFixture::with(
            [],
            fn (string $root): ?string => (new GitVersionReader())->read($root),
        );

        expect($version)->toBeNull();
    });
});

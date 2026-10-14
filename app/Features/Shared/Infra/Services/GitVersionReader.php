<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Reads the current version straight from a repository's `.git` directory:
 * the exact tag of the checked-out commit when one points at it, otherwise
 * the short (7-character) commit SHA. Used where the `git` binary is not
 * available (the dev web container) — production derives the same value at
 * image build time.
 */
final class GitVersionReader
{
    private const SHORT_SHA_LENGTH = 7;
    private const TAGS_PREFIX = 'refs/tags/';

    public function read(string $basePath): ?string
    {
        $gitDir = $this->resolveGitDir($basePath);

        if ($gitDir === null) {
            return null;
        }

        $headSha = $this->resolveHeadSha($gitDir);

        if ($headSha === null) {
            return null;
        }

        return $this->findExactTag($gitDir, $headSha)
            ?? substr($headSha, 0, self::SHORT_SHA_LENGTH);
    }

    private function resolveGitDir(string $basePath): ?string
    {
        $path = rtrim($basePath, '/') . '/.git';

        if (is_dir($path)) {
            return $path;
        }

        if (! is_file($path)) {
            return null;
        }

        $pointer = trim((string) file_get_contents($path));

        if (! str_starts_with($pointer, 'gitdir:')) {
            return null;
        }

        $target = trim(substr($pointer, strlen('gitdir:')));

        if ($target === '') {
            return null;
        }

        if (! str_starts_with($target, '/')) {
            $target = rtrim($basePath, '/') . '/' . $target;
        }

        return is_dir($target) ? $target : null;
    }

    private function resolveHeadSha(string $gitDir): ?string
    {
        $headFile = $gitDir . '/HEAD';

        if (! is_file($headFile)) {
            return null;
        }

        $head = trim((string) file_get_contents($headFile));

        if (! str_starts_with($head, 'ref:')) {
            return $this->isSha($head) ? $head : null;
        }

        return $this->resolveRef($gitDir, trim(substr($head, strlen('ref:'))));
    }

    private function resolveRef(string $gitDir, string $ref): ?string
    {
        $loose = $gitDir . '/' . $ref;

        if (is_file($loose)) {
            $sha = trim((string) file_get_contents($loose));

            return $this->isSha($sha) ? $sha : null;
        }

        return $this->packedRefs($gitDir)[$ref] ?? null;
    }

    private function findExactTag(string $gitDir, string $headSha): ?string
    {
        $matching = [];

        foreach ($this->tagShas($gitDir) as $tag => $sha) {
            if ($sha === $headSha) {
                $matching[] = $tag;
            }
        }

        if ($matching === []) {
            return null;
        }

        usort(
            $matching,
            static fn (string $a, string $b): int => version_compare(ltrim($b, 'v'), ltrim($a, 'v')),
        );

        return $matching[0];
    }

    /**
     * @return array<string, string> tag name (without the `refs/tags/` prefix) => commit SHA
     */
    private function tagShas(string $gitDir): array
    {
        $refs = $this->packedRefs($gitDir);
        $tagDir = $gitDir . '/' . self::TAGS_PREFIX;

        if (! is_dir($tagDir)) {
            return $this->onlyTags($refs);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tagDir, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            $name = substr($file->getPathname(), strlen($tagDir));
            $sha = $this->peelLooseTag($gitDir, (string) file_get_contents($file->getPathname()));

            if ($sha !== null) {
                $refs[self::TAGS_PREFIX . $name] = $sha;
            }
        }

        return $this->onlyTags($refs);
    }

    /**
     * @param  array<string, string>  $refs
     * @return array<string, string>
     */
    private function onlyTags(array $refs): array
    {
        $tags = [];

        foreach ($refs as $ref => $sha) {
            if (str_starts_with($ref, self::TAGS_PREFIX)) {
                $tags[substr($ref, strlen(self::TAGS_PREFIX))] = $sha;
            }
        }

        return $tags;
    }

    private function peelLooseTag(string $gitDir, string $sha): ?string
    {
        $sha = trim($sha);

        if (! $this->isSha($sha)) {
            return null;
        }

        $object = $this->readLooseObject($gitDir, $sha);

        if ($object === null) {
            return $sha;
        }

        $separator = strpos($object, "\0");
        $firstLine = strtok($separator === false ? $object : substr($object, $separator + 1), "\n");

        if (! is_string($firstLine) || ! str_starts_with($firstLine, 'object ')) {
            return $sha;
        }

        $target = substr($firstLine, strlen('object '));

        return $this->isSha($target) ? $target : $sha;
    }

    private function readLooseObject(string $gitDir, string $sha): ?string
    {
        $path = sprintf('%s/objects/%s/%s', $gitDir, substr($sha, 0, 2), substr($sha, 2));

        if (! is_file($path)) {
            return null;
        }

        $raw = (string) file_get_contents($path);

        return function_exists('zlib_decode') ? (zlib_decode($raw) ?: null) : null;
    }

    /**
     * @return array<string, string>
     */
    private function packedRefs(string $gitDir): array
    {
        $file = $gitDir . '/packed-refs';

        if (! is_file($file)) {
            return [];
        }

        $refs = [];
        $lastRef = null;

        foreach (preg_split('/\R/', (string) file_get_contents($file)) ?: [] as $line) {
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, '^')) {
                $this->recordPeeledRef($refs, $lastRef, substr($line, 1));
                continue;
            }

            $parts = preg_split('/\s+/', trim($line), 2) ?: [];
            $lastRef = count($parts) === 2 ? $parts[1] : null;

            if ($lastRef !== null && $this->isSha($parts[0])) {
                $refs[$lastRef] = $parts[0];
            }
        }

        return $refs;
    }

    /**
     * @param  array<string, string>  $refs
     */
    private function recordPeeledRef(array &$refs, ?string $ref, string $sha): void
    {
        if ($ref !== null && $this->isSha($sha)) {
            $refs[$ref] = $sha;
        }
    }

    private function isSha(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{40}([0-9a-f]{24})?$/', $value);
    }
}

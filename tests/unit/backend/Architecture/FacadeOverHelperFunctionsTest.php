<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Support\Architecture\PhpTokenStream;

const FACADE_BACKED_HELPERS = [
    'app',
    'app_path',
    'back',
    'base_path',
    'config',
    'database_path',
    'lang_path',
    'now',
    'public_path',
    'redirect',
    'request',
    'resource_path',
    'response',
    'session',
    'storage_path',
    'url',
    'validator',
];

const SCANNED_DIRECTORIES = ['app', 'routes', 'database'];

/**
 * Source lines where a facade-backed Laravel global helper is called. Method,
 * static and definition calls are ignored, and comments/strings never produce
 * a token matching T_STRING.
 *
 * @return list<int>
 */
function findFacadeHelperCalls(string $source): array
{
    $tokens = token_get_all($source);
    $count = count($tokens);
    $lines = [];

    for ($i = 0; $i < $count; $i++) {
        $line = facadeHelperCallLine($tokens, $i);

        if ($line !== null) {
            $lines[] = $line;
        }
    }

    return $lines;
}

function facadeHelperCallLine(array $tokens, int $index): ?int
{
    $token = $tokens[$index];

    if (!is_array($token) || $token[0] !== T_STRING) {
        return null;
    }

    if (!in_array($token[1], FACADE_BACKED_HELPERS, true)) {
        return null;
    }

    $open = PhpTokenStream::significantAfter($tokens, $index + 1);
    if ($open === -1 || $tokens[$open] !== '(') {
        return null;
    }

    $before = PhpTokenStream::significantBefore($tokens, $index - 1);
    if ($before !== -1 && PhpTokenStream::precedesFunctionCall($tokens[$before])) {
        return null;
    }

    return $token[2];
}

/**
 * @return list<string>
 */
function facadeHelperViolations(string $root): array
{
    $violations = [];

    foreach (SCANNED_DIRECTORIES as $directory) {
        $directoryPath = "{$root}/{$directory}";
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directoryPath));

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($root) + 1);
            $source = (string) file_get_contents($file->getPathname());

            foreach (findFacadeHelperCalls($source) as $line) {
                $violations[] = "{$relativePath}:{$line}";
            }
        }
    }

    return $violations;
}

it('uses imported Laravel facades instead of global helper functions', function (): void {
    $violations = facadeHelperViolations(dirname(__DIR__, 4));

    expect($violations)->toBe([]);
});

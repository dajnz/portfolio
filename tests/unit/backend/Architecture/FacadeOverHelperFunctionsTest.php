<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

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

/**
 * Returns the source lines where a facade-backed Laravel global helper is
 * called. Tokens preceded by `->`, `::`, `function`, `new` or a namespace
 * separator are method/static/definition calls, not helper calls; comments and
 * string literals never produce tokens matching T_STRING.
 *
 * @return list<int>
 */
function findFacadeHelperCalls(string $source): array
{
    $tokens = token_get_all($source);
    $count = count($tokens);
    $skippable = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];
    $skipBefore = [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, T_NS_SEPARATOR, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED];

    $lines = [];
    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token) || $token[0] !== T_STRING || !in_array($token[1], FACADE_BACKED_HELPERS, true)) {
            continue;
        }

        $next = $i + 1;
        while ($next < $count && is_array($tokens[$next]) && in_array($tokens[$next][0], $skippable, true)) {
            $next++;
        }
        if (($tokens[$next] ?? null) !== '(') {
            continue;
        }

        $previous = $i - 1;
        while ($previous >= 0 && is_array($tokens[$previous]) && in_array($tokens[$previous][0], $skippable, true)) {
            $previous--;
        }
        $before = $tokens[$previous] ?? null;
        if (is_array($before) && in_array($before[0], $skipBefore, true)) {
            continue;
        }

        $lines[] = $token[2];
    }

    return $lines;
}

it('uses imported Laravel facades instead of global helper functions', function (): void {
    $root = dirname(__DIR__, 4);
    $violations = [];

    foreach (['app', 'routes', 'database'] as $directory) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$directory}"));

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($root) + 1);

            foreach (findFacadeHelperCalls((string) file_get_contents($file->getPathname())) as $line) {
                $violations[] = "{$relative}:{$line}";
            }
        }
    }

    expect($violations)->toBe([]);
});

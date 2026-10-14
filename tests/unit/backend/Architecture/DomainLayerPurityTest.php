<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Support\Architecture\PhpTokenStream;

const LARAVEL_HELPER_PATHS = [
    'vendor/laravel/framework/src/Illuminate/Collections/helpers.php',
    'vendor/laravel/framework/src/Illuminate/Foundation/helpers.php',
    'vendor/laravel/framework/src/Illuminate/Support/helpers.php',
    'vendor/laravel/framework/src/Illuminate/Reflection/helpers.php',
];

const FRAMEWORK_NAMESPACE_PATTERN = '/(^|\\\\)(Illuminate|Carbon)(\\\\|$)/';

const FUNCTION_NAME_BOUNDARY_TOKENS = [
    T_WHITESPACE,
    T_COMMENT,
    T_DOC_COMMENT,
    T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
    T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
];

const QUALIFIED_NAME_TOKENS = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED];

/**
 * Every global function declared by the framework's helper files, derived from
 * the installed framework so new helpers are covered without editing this test.
 *
 * @return list<string>
 */
function laravelHelperNames(): array
{
    $root = dirname(__DIR__, 4);
    $helpers = [];

    foreach (LARAVEL_HELPER_PATHS as $relativePath) {
        $path = "{$root}/{$relativePath}";
        if (!is_file($path)) {
            continue;
        }

        foreach (declaredFunctionNames((string) file_get_contents($path)) as $name) {
            $helpers[$name] = true;
        }
    }

    return array_keys($helpers);
}

/**
 * @return list<string>
 */
function declaredFunctionNames(string $source): array
{
    $tokens = token_get_all($source);
    $count = count($tokens);
    $names = [];

    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
            continue;
        }

        $index = $i + 1;
        while ($index < $count && isSkippedForFunctionName($tokens[$index])) {
            $index++;
        }

        if ($index < $count && is_array($tokens[$index]) && $tokens[$index][0] === T_STRING) {
            $names[] = $tokens[$index][1];
        }
    }

    return $names;
}

function isSkippedForFunctionName(mixed $token): bool
{
    return is_array($token) && in_array($token[0], FUNCTION_NAME_BOUNDARY_TOKENS, true);
}

/**
 * Framework dependencies in one domain-layer source: framework namespace
 * references, Laravel helper calls and non-domain `App\` imports. Comments and
 * string literals yield no matching tokens, so docblock mentions are ignored.
 *
 * @param  list<string>  $laravelHelpers
 * @return list<string>
 */
function sourceViolations(string $source, array $laravelHelpers): array
{
    $tokens = token_get_all($source);
    $count = count($tokens);
    $violations = [];

    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i])) {
            continue;
        }

        $violation = frameworkReferenceViolation($tokens[$i]);

        if ($violation === null && $tokens[$i][0] === T_USE) {
            $violation = nonDomainImportViolation($tokens, $i);
        }

        if ($violation === null && $tokens[$i][0] === T_STRING) {
            $violation = laravelHelperCallViolation($tokens, $i, $laravelHelpers);
        }

        if ($violation !== null) {
            $violations[] = $violation;
        }
    }

    return $violations;
}

/**
 * @param  array{0: int, 1: string, 2: int}  $token
 */
function frameworkReferenceViolation(array $token): ?string
{
    if (!in_array($token[0], QUALIFIED_NAME_TOKENS, true)) {
        return null;
    }

    if (preg_match(FRAMEWORK_NAMESPACE_PATTERN, $token[1]) !== 1) {
        return null;
    }

    return "line {$token[2]}: framework type `{$token[1]}`";
}

function nonDomainImportViolation(array $tokens, int $index): ?string
{
    $target = PhpTokenStream::significantAfter($tokens, $index + 1);

    if ($target === -1 || !is_array($tokens[$target])) {
        return null;
    }

    if ($tokens[$target][0] === T_FUNCTION || $tokens[$target][0] === T_CONST) {
        return null;
    }

    $imported = $tokens[$target][1];
    if (!str_starts_with($imported, 'App\\') || str_contains($imported, '\\Domain\\')) {
        return null;
    }

    return "line {$tokens[$target][2]}: non-domain import `{$imported}`";
}

/**
 * @param  list<string>  $laravelHelpers
 */
function laravelHelperCallViolation(array $tokens, int $index, array $laravelHelpers): ?string
{
    $token = $tokens[$index];

    if (!in_array($token[1], $laravelHelpers, true)) {
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

    return "line {$token[2]}: Laravel helper `{$token[1]}()`";
}

/**
 * @param  list<string>  $laravelHelpers
 * @return list<string>
 */
function domainLayerViolations(string $root, array $laravelHelpers): array
{
    $violations = [];

    foreach (glob("{$root}/app/Features/*/Domain") as $domainDirectory) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($domainDirectory));

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($root) + 1);
            $source = (string) file_get_contents($file->getPathname());

            foreach (sourceViolations($source, $laravelHelpers) as $violation) {
                $violations[] = "{$relativePath} {$violation}";
            }
        }
    }

    return $violations;
}

it('keeps the domain layer free of Laravel dependencies', function (): void {
    $helpers = laravelHelperNames();
    expect($helpers)->not->toBeEmpty();

    $violations = domainLayerViolations(dirname(__DIR__, 4), $helpers);

    expect($violations)->toBe([]);
});

<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Support\Architecture\LayerPurityScanner;
use Tests\Support\Architecture\PhpTokenStream;

const COMMAND_JOB_USE_CASE_ALLOWLIST = [
    'app/Console/Commands/ExportLangCommand.php',
];

const COMMAND_JOB_DIRECT_ACCESS_FORBIDDEN = [
    'DB',
    'Storage',
    'Bus',
    'Config',
];

/**
 * Console commands and queued jobs are thin entry points: persistence,
 * orchestration and policy live in app use cases. They may use framework
 * console/queue types and Infra utility services, but never domain
 * repositories, Eloquent models or Infra repositories.
 */
function commandJobForbiddenImport(string $imported): ?string
{
    if (preg_match('/^App\\\\Features\\\\[A-Za-z]+\\\\Domain\\\\RepositoryInterfaces\\\\/', $imported) === 1) {
        return "repository import `{$imported}`";
    }

    if (preg_match('/^App\\\\Features\\\\[A-Za-z]+\\\\Infra\\\\(Models|Repositories)\\\\/', $imported) === 1) {
        return "persistence import `{$imported}`";
    }

    if (preg_match('/^App\\\\Features\\\\[A-Za-z]+\\\\Infra\\\\Jobs\\\\/', $imported) === 1) {
        return "job import `{$imported}`";
    }

    return null;
}

/**
 * @return list<string>
 */
function commandJobFiles(string $root): array
{
    $patterns = [
        'app/Features/*/Infra/Commands',
        'app/Features/*/Infra/Jobs',
        'app/Console/Commands',
    ];

    $files = [];

    foreach ($patterns as $pattern) {
        foreach (glob($root.'/'.$pattern) as $directory) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = substr($file->getPathname(), strlen($root) + 1);
                }
            }
        }
    }

    sort($files);

    return $files;
}

/**
 * @return list<string>
 */
function commandJobDirectAccessViolations(string $root): array
{
    $violations = [];
    $nameTokens = [
        T_STRING,
        T_NAME_FULLY_QUALIFIED,
    ];

    foreach (commandJobFiles($root) as $relativePath) {
        $tokens = token_get_all((string) file_get_contents($root.'/'.$relativePath));
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (! is_array($tokens[$i]) || ! in_array($tokens[$i][0], $nameTokens, true)) {
                continue;
            }

            $name = ltrim($tokens[$i][1], '\\');

            if (! in_array($name, COMMAND_JOB_DIRECT_ACCESS_FORBIDDEN, true)) {
                continue;
            }

            $next = PhpTokenStream::significantAfter($tokens, $i + 1);

            if ($next !== -1 && $tokens[$next] === T_DOUBLE_COLON) {
                $violations[] = "{$relativePath} line {$tokens[$i][2]}: direct `{$name}::` access";
            }
        }
    }

    sort($violations);

    return $violations;
}

/**
 * @return list<string>
 */
function commandJobUseCaseViolations(string $root): array
{
    $violations = [];

    foreach (commandJobFiles($root) as $relativePath) {
        if (in_array($relativePath, COMMAND_JOB_USE_CASE_ALLOWLIST, true)) {
            continue;
        }

        $tokens = token_get_all((string) file_get_contents($root.'/'.$relativePath));

        if (! commandJobHasUseCaseDependency($tokens)) {
            $violations[] = "{$relativePath} does not depend on a use case";
        }
    }

    sort($violations);

    return $violations;
}

/**
 * @param  array<int, mixed>  $tokens
 */
function commandJobHasUseCaseDependency(array $tokens): bool
{
    $count = count($tokens);
    $nameTokens = [
        T_STRING,
        T_NAME_FULLY_QUALIFIED,
    ];
    $lifecycleMethods = [
        '__construct',
        'handle',
    ];

    for ($i = 0; $i < $count; $i++) {
        if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
            continue;
        }

        $nameIndex = PhpTokenStream::significantAfter($tokens, $i + 1);

        if ($nameIndex === -1 || ! is_array($tokens[$nameIndex])) {
            continue;
        }

        if (! in_array($tokens[$nameIndex][1], $lifecycleMethods, true)) {
            continue;
        }

        for ($j = $nameIndex + 1; $j < $count; $j++) {
            if ($tokens[$j] === '{' || $tokens[$j] === ';') {
                break;
            }

            if (! is_array($tokens[$j]) || ! in_array($tokens[$j][0], $nameTokens, true)) {
                continue;
            }

            if (str_ends_with(ltrim($tokens[$j][1], '\\'), 'UseCase')) {
                return true;
            }
        }
    }

    return false;
}

it('keeps commands and jobs free of repository, model and job imports', function (): void {
    $root = dirname(__DIR__, 4);
    $scanner = new LayerPurityScanner($root);
    $patterns = [
        'app/Features/*/Infra/Commands',
        'app/Features/*/Infra/Jobs',
        'app/Console/Commands',
    ];

    $violations = [];

    foreach ($patterns as $pattern) {
        $violations = [
            ...$violations,
            ...$scanner->scan($pattern, 'Tests\Unit\Backend\Architecture\commandJobForbiddenImport', checkFrameworkSurface: false),
        ];
    }

    sort($violations);

    expect($violations)->toBe([]);
});

it('keeps commands and jobs free of direct persistence and queue access', function (): void {
    expect(commandJobDirectAccessViolations(dirname(__DIR__, 4)))->toBe([]);
});

it('requires every command and job to depend on a use case', function (): void {
    expect(commandJobUseCaseViolations(dirname(__DIR__, 4)))->toBe([]);
});

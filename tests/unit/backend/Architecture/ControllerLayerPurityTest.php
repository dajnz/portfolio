<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Support\Architecture\LayerPurityScanner;
use Tests\Support\Architecture\PhpTokenStream;

const CONTROLLER_INFRA_SERVICE_ALLOWLIST = [
    'AdminAuthService',
    'PerLocaleValues',
    'ProjectDetailPropsBuilder',
];

const CONTROLLER_DIRECT_ACCESS_ALLOWLIST = [
    'app/Features/Resume/Infra/Controllers/PublicResumeController.php' => ['Storage'],
    'app/Features/Shared/Infra/Controllers/HealthController.php' => ['DB', 'Storage'],
];

const CONTROLLER_ORCHESTRATION_ALLOWLIST = [
    'app/Features/Shared/Infra/Controllers/HealthController.php',
];

/**
 * Controllers are transport-only (architecture.md): they translate the HTTP
 * request into use-case calls and the use-case results into response shapes.
 * They never reach past the app layer into persistence — no domain repository
 * interfaces, no Eloquent models and no Infra repositories. The framework
 * surface (HTTP types, facades, helpers) and domain types used for mapping are
 * expected here, so only the imports are checked.
 */
function controllerLayerForbiddenImport(string $imported): ?string
{
    if (preg_match('/^App\\\\Features\\\\[A-Za-z]+\\\\Domain\\\\RepositoryInterfaces\\\\/', $imported) === 1) {
        return "repository import `{$imported}`";
    }

    if (preg_match('/^App\\\\Features\\\\[A-Za-z]+\\\\Infra\\\\(Models|Repositories)\\\\/', $imported) === 1) {
        return "persistence import `{$imported}`";
    }

    return null;
}

function controllerInfraServiceImportViolation(string $imported): ?string
{
    if (preg_match('/^App\\\\Features\\\\[A-Za-z]+\\\\Infra\\\\Services\\\\([A-Za-z]+)$/', $imported, $matches) !== 1) {
        return null;
    }

    if (in_array($matches[1], CONTROLLER_INFRA_SERVICE_ALLOWLIST, true)) {
        return null;
    }

    return "non-presentation Infra service import `{$imported}`";
}

/**
 * @return list<string>
 */
function controllerDirectDataAccessViolations(string $root): array
{
    $violations = [];

    foreach (glob($root.'/app/Features/*/Infra/Controllers') as $directory) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($root) + 1);
            $allowedFacades = CONTROLLER_DIRECT_ACCESS_ALLOWLIST[$relativePath] ?? [];

            $tokens = token_get_all((string) file_get_contents($file->getPathname()));
            $count = count($tokens);

            for ($i = 0; $i < $count; $i++) {
                if (! is_array($tokens[$i]) || ! in_array($tokens[$i][0], [T_STRING, T_NAME_FULLY_QUALIFIED], true)) {
                    continue;
                }

                $name = ltrim($tokens[$i][1], '\\');

                if (! in_array($name, ['DB', 'Storage'], true)) {
                    continue;
                }

                if (in_array($name, $allowedFacades, true)) {
                    continue;
                }

                $next = PhpTokenStream::significantAfter($tokens, $i + 1);

                if ($next !== -1 && $tokens[$next] === T_DOUBLE_COLON) {
                    $violations[] = "{$relativePath} line {$tokens[$i][2]}: direct `{$name}::` access";
                }
            }
        }
    }

    sort($violations);

    return $violations;
}

/**
 * @return list<string>
 */
function controllerOrchestrationViolations(string $root): array
{
    $violations = [];

    foreach (glob($root.'/app/Features/*/Infra/Controllers') as $directory) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($root) + 1);

            if (in_array($relativePath, CONTROLLER_ORCHESTRATION_ALLOWLIST, true)) {
                continue;
            }

            $tokens = token_get_all((string) file_get_contents($file->getPathname()));

            if (! controllerHasUseCaseDependency($tokens)) {
                $violations[] = "{$relativePath} does not inject a use case";
            }
        }
    }

    sort($violations);

    return $violations;
}

/**
 * @param  array<int, mixed>  $tokens
 */
function controllerHasUseCaseDependency(array $tokens): bool
{
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_PRIVATE) {
            continue;
        }

        $readonly = PhpTokenStream::significantAfter($tokens, $i + 1);

        if ($readonly === -1 || ! is_array($tokens[$readonly]) || $tokens[$readonly][0] !== T_READONLY) {
            continue;
        }

        $type = PhpTokenStream::significantAfter($tokens, $readonly + 1);

        if ($type === -1 || ! is_array($tokens[$type]) || $tokens[$type][0] !== T_STRING) {
            continue;
        }

        if (! str_ends_with($tokens[$type][1], 'UseCase')) {
            continue;
        }

        $variable = PhpTokenStream::significantAfter($tokens, $type + 1);

        if ($variable !== -1 && is_array($tokens[$variable]) && $tokens[$variable][0] === T_VARIABLE) {
            return true;
        }
    }

    return false;
}

it('keeps controllers free of repository, Eloquent-model and Infra-repository imports', function (): void {
    $scanner = new LayerPurityScanner(dirname(__DIR__, 4));

    $violations = $scanner->scan(
        'app/Features/*/Infra/Controllers',
        'Tests\Unit\Backend\Architecture\controllerLayerForbiddenImport',
        checkFrameworkSurface: false,
    );

    expect($violations)->toBe([]);
});

it('keeps controllers limited to the presentation Infra services', function (): void {
    $scanner = new LayerPurityScanner(dirname(__DIR__, 4));

    $violations = $scanner->scan(
        'app/Features/*/Infra/Controllers',
        'Tests\Unit\Backend\Architecture\controllerInfraServiceImportViolation',
        checkFrameworkSurface: false,
    );

    expect($violations)->toBe([]);
});

it('keeps controllers free of direct DB and storage access', function (): void {
    expect(controllerDirectDataAccessViolations(dirname(__DIR__, 4)))->toBe([]);
});

it('makes every controller orchestrate through a use case', function (): void {
    expect(controllerOrchestrationViolations(dirname(__DIR__, 4)))->toBe([]);
});

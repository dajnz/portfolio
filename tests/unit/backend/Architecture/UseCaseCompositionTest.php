<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Support\Architecture\PhpTokenStream;

/**
 * Class names that end with `UseCase` but are not feature-slice use cases.
 */
const NON_USE_CASE_CLASS_NAMES = ['BaseUseCase'];

const USE_CASE_NAME_TOKEN_TYPES = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED];

/**
 * The short class name of a possibly qualified name
 * (`App\Features\Blog\App\UseCases\StoreArticleUseCase` → `StoreArticleUseCase`).
 */
function useCaseCompositionShortName(string $name): string
{
    $parts = explode('\\', $name);

    return (string) end($parts);
}

/**
 * The class declared in the file, or null when the file declares none.
 *
 * @param  array<int, mixed>  $tokens
 */
function useCaseCompositionDeclaredClass(array $tokens): ?string
{
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_CLASS) {
            continue;
        }

        $name = PhpTokenStream::significantAfter($tokens, $i + 1);

        if ($name === -1 || !is_array($tokens[$name]) || $tokens[$name][0] !== T_STRING) {
            continue;
        }

        return $tokens[$name][1];
    }

    return null;
}

/**
 * Class references that resolve to a use case, ignoring the file's own
 * declaration and the abstract base. Comments and docblocks produce no name
 * tokens, so prose mentions are ignored.
 *
 * @param  array<int, mixed>  $tokens
 * @return list<string>
 */
function useCaseCompositionViolations(array $tokens, ?string $ownClass): array
{
    $violations = [];

    foreach ($tokens as $token) {
        if (!is_array($token) || !in_array($token[0], USE_CASE_NAME_TOKEN_TYPES, true)) {
            continue;
        }

        $shortName = useCaseCompositionShortName($token[1]);

        if (!str_ends_with($shortName, 'UseCase')) {
            continue;
        }

        if ($shortName === $ownClass || in_array($shortName, NON_USE_CASE_CLASS_NAMES, true)) {
            continue;
        }

        $violations[] = "line {$token[2]}: use-case reference `{$token[1]}`";
    }

    return $violations;
}

/**
 * @return list<string>
 */
function useCaseCompositionScan(string $root, string $directoryPattern, bool $ownClassIsAllowed): array
{
    $violations = [];

    foreach (glob("{$root}/{$directoryPattern}") as $directory) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($root) + 1);
            $tokens = token_get_all((string) file_get_contents($file->getPathname()));
            $ownClass = $ownClassIsAllowed ? useCaseCompositionDeclaredClass($tokens) : null;

            foreach (useCaseCompositionViolations($tokens, $ownClass) as $violation) {
                $violations[] = "{$relativePath} {$violation}";
            }
        }
    }

    sort($violations);

    return $violations;
}

/**
 * Use cases are single entry points: they may depend on domain types, app
 * contracts, app DTOs and app services, but never on another use case. Reusable
 * algorithms shared by several entry points live in `App/Services/`.
 */
it('keeps use cases free of other use cases', function (): void {
    $violations = useCaseCompositionScan(
        dirname(__DIR__, 4),
        'app/Features/*/App/UseCases',
        ownClassIsAllowed: true,
    );

    expect($violations)->toBe([]);
});

/**
 * The reverse direction: an app service is a reusable capability, so it may not
 * depend on a use case either.
 */
it('keeps app services free of use cases', function (): void {
    $violations = useCaseCompositionScan(
        dirname(__DIR__, 4),
        'app/Features/*/App/Services',
        ownClassIsAllowed: false,
    );

    expect($violations)->toBe([]);
});

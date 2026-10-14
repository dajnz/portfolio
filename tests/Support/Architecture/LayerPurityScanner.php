<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Scans a backend layer for framework dependencies and imports outside the
 * layer's allowed surface. Framework namespace references and Laravel global
 * helper calls are layer-independent; the forbidden imports depend on the
 * layer, so the caller supplies a predicate that maps an imported FQN to a
 * violation message (or null when the import is allowed).
 *
 * Token based, so comments and docblocks never produce a matching token.
 */
final class LayerPurityScanner
{
    private const FRAMEWORK_NAMESPACE_PATTERN = '/(^|\\\\)(Illuminate|Carbon|Laravel)(\\\\|$)/';

    private const QUALIFIED_NAME_TOKENS = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED];

    private const FUNCTION_NAME_BOUNDARY_TOKENS = [
        T_WHITESPACE,
        T_COMMENT,
        T_DOC_COMMENT,
        T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
        T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
    ];

    private const HELPER_SOURCES = [
        'vendor/laravel/framework/src/Illuminate/Collections/helpers.php',
        'vendor/laravel/framework/src/Illuminate/Foundation/helpers.php',
        'vendor/laravel/framework/src/Illuminate/Support/helpers.php',
        'vendor/laravel/framework/src/Illuminate/Reflection/helpers.php',
    ];

    /**
     * @var list<string>
     */
    private readonly array $helperNames;

    public function __construct(private readonly string $root)
    {
        $this->helperNames = self::discoverHelperNames($root);
    }

    /**
     * Every global function declared by the framework's helper files, derived
     * from the installed framework so new helpers are covered without editing
     * the caller.
     *
     * @return list<string>
     */
    public function helperNames(): array
    {
        return $this->helperNames;
    }

    /**
     * @param  callable(string): ?string  $forbiddenImport  Maps an imported FQN to a violation message, or null when allowed.
     * @param  bool  $checkFrameworkSurface  When false, only imports are checked — the framework
     *                                       types and helpers a transport layer is expected to use are allowed.
     * @return list<string>
     */
    public function scan(
        string $layerDirectoryPattern,
        callable $forbiddenImport,
        bool $checkFrameworkSurface = true,
    ): array {
        $violations = [];

        foreach (glob($this->root.'/'.$layerDirectoryPattern) as $directory) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

            foreach ($files as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $relativePath = substr($file->getPathname(), strlen($this->root) + 1);
                $source = (string) file_get_contents($file->getPathname());

                foreach ($this->sourceViolations($source, $forbiddenImport, $checkFrameworkSurface) as $violation) {
                    $violations[] = "{$relativePath} {$violation}";
                }
            }
        }

        sort($violations);

        return $violations;
    }

    /**
     * @param  callable(string): ?string  $forbiddenImport
     * @return list<string>
     */
    private function sourceViolations(string $source, callable $forbiddenImport, bool $checkFrameworkSurface): array
    {
        $tokens = token_get_all($source);
        $count = count($tokens);
        $violations = [];

        for ($i = 0; $i < $count; $i++) {
            if (! is_array($tokens[$i])) {
                continue;
            }

            $violation = $checkFrameworkSurface ? $this->referenceViolation($tokens[$i]) : null;

            if ($violation === null && $tokens[$i][0] === T_USE) {
                $violation = $this->importViolation($tokens, $i, $forbiddenImport);
            }

            if ($checkFrameworkSurface && $violation === null && $tokens[$i][0] === T_STRING) {
                $violation = $this->helperCallViolation($tokens, $i);
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
    private function referenceViolation(array $token): ?string
    {
        if (! in_array($token[0], self::QUALIFIED_NAME_TOKENS, true)) {
            return null;
        }

        if (preg_match(self::FRAMEWORK_NAMESPACE_PATTERN, $token[1]) !== 1) {
            return null;
        }

        return "line {$token[2]}: framework type `{$token[1]}`";
    }

    /**
     * @param  array<int, mixed>  $tokens
     * @param  callable(string): ?string  $forbiddenImport
     */
    private function importViolation(array $tokens, int $index, callable $forbiddenImport): ?string
    {
        $target = PhpTokenStream::significantAfter($tokens, $index + 1);

        if ($target === -1 || ! is_array($tokens[$target])) {
            return null;
        }

        if ($tokens[$target][0] === T_FUNCTION || $tokens[$target][0] === T_CONST) {
            return null;
        }

        $message = $forbiddenImport($tokens[$target][1]);

        if ($message === null) {
            return null;
        }

        return "line {$tokens[$target][2]}: {$message}";
    }

    /**
     * @param  array<int, mixed>  $tokens
     */
    private function helperCallViolation(array $tokens, int $index): ?string
    {
        $token = $tokens[$index];

        if (! in_array($token[1], $this->helperNames, true)) {
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
     * @return list<string>
     */
    private static function discoverHelperNames(string $root): array
    {
        $names = [];

        foreach (self::HELPER_SOURCES as $relativePath) {
            $path = "{$root}/{$relativePath}";

            if (! is_file($path)) {
                continue;
            }

            foreach (self::declaredFunctionNames((string) file_get_contents($path)) as $name) {
                $names[$name] = true;
            }
        }

        return array_keys($names);
    }

    /**
     * @return list<string>
     */
    private static function declaredFunctionNames(string $source): array
    {
        $tokens = token_get_all($source);
        $count = count($tokens);
        $names = [];

        for ($i = 0; $i < $count; $i++) {
            if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
                continue;
            }

            $index = $i + 1;

            while ($index < $count && self::isFunctionNameBoundary($tokens[$index])) {
                $index++;
            }

            if ($index < $count && is_array($tokens[$index]) && $tokens[$index][0] === T_STRING) {
                $names[] = $tokens[$index][1];
            }
        }

        return $names;
    }

    private static function isFunctionNameBoundary(mixed $token): bool
    {
        return is_array($token) && in_array($token[0], self::FUNCTION_NAME_BOUNDARY_TOKENS, true);
    }
}
